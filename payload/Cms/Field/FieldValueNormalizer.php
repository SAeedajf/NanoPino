<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Field;

use DateTimeImmutable;
use JsonException;

final class FieldValueNormalizer
{
    public function normalize(FieldDefinition $definition, mixed $value): mixed
    {
        if ($value === null) {
            if ($definition->required) {
                throw new FieldValidationException(
                    'Required field is null: ' . $definition->identifier()
                );
            }
            return null;
        }

        if (!$definition->writable()) {
            throw new FieldValidationException(
                'Field is not directly writable: ' . $definition->identifier()
            );
        }

        $normalized = match ($definition->type) {
            FieldType::Text,
            FieldType::Textarea,
            FieldType::RichText => $this->string($value, $definition),
            FieldType::Number => $this->number($value, $definition),
            FieldType::Boolean => $this->boolean($value, $definition),
            FieldType::Date => $this->date($value, $definition),
            FieldType::Select => $this->select($value, $definition),
            FieldType::Relation,
            FieldType::Taxonomy => $this->ids($value, $definition),
            FieldType::Media => $definition->multiple
                ? $this->ids($value, $definition)
                : $this->id($value, $definition),
            FieldType::Gallery => $this->ids($value, $definition),
            FieldType::Json => $this->json($value, $definition),
            FieldType::Repeater => $this->repeater($value, $definition),
            FieldType::Group => $this->group($value, $definition),
        };

        if ($definition->validator !== null) {
            $result = ($definition->validator)($normalized);
            if ($result === false) {
                throw new FieldValidationException(
                    'Field validation failed: ' . $definition->identifier()
                );
            }
            if (is_string($result) && $result !== '') {
                throw new FieldValidationException($result);
            }
        }

        return $normalized;
    }

    private function string(mixed $value, FieldDefinition $definition): string
    {
        if (!is_string($value) && !is_scalar($value)) {
            throw new FieldValidationException('Expected string for ' . $definition->identifier());
        }
        $value = (string)$value;
        $max = isset($definition->options['max_length'])
            ? (int)$definition->options['max_length']
            : null;
        if ($max !== null && $this->length($value) > $max) {
            throw new FieldValidationException('Field exceeds max length: ' . $definition->identifier());
        }
        return $value;
    }

    private function number(mixed $value, FieldDefinition $definition): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }
        throw new FieldValidationException('Expected number for ' . $definition->identifier());
    }

    private function boolean(mixed $value, FieldDefinition $definition): bool
    {
        if (is_bool($value)) return $value;
        if (in_array($value, [1, '1', 'true', 'yes', 'on'], true)) return true;
        if (in_array($value, [0, '0', 'false', 'no', 'off'], true)) return false;
        throw new FieldValidationException('Expected boolean for ' . $definition->identifier());
    }

    private function date(mixed $value, FieldDefinition $definition): string
    {
        if (!$value instanceof \DateTimeInterface && !is_string($value)) {
            throw new FieldValidationException('Expected date for ' . $definition->identifier());
        }

        try {
            $date = $value instanceof \DateTimeInterface
                ? DateTimeImmutable::createFromInterface($value)
                : new DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new FieldValidationException('Invalid date for ' . $definition->identifier());
        }

        return $date->format(DATE_ATOM);
    }

    private function select(mixed $value, FieldDefinition $definition): mixed
    {
        $allowed = is_array($definition->options['choices'] ?? null)
            ? array_keys($definition->options['choices'])
            : [];

        if ($definition->multiple) {
            if (!is_array($value)) {
                throw new FieldValidationException('Expected select list for ' . $definition->identifier());
            }
            $result = array_values($value);
            foreach ($result as $item) {
                if (!is_scalar($item) || ($allowed !== [] && !in_array((string)$item, $allowed, true))) {
                    throw new FieldValidationException('Invalid select option for ' . $definition->identifier());
                }
            }
            return $result;
        }

        if (!is_scalar($value)) {
            throw new FieldValidationException('Expected select scalar for ' . $definition->identifier());
        }

        if ($allowed !== [] && !in_array((string)$value, $allowed, true)) {
            throw new FieldValidationException('Invalid select option for ' . $definition->identifier());
        }

        return $value;
    }

    /** @return list<int> */
    private function ids(mixed $value, FieldDefinition $definition): array
    {
        if (!is_array($value)) {
            if ($definition->multiple) {
                throw new FieldValidationException('Expected id list for ' . $definition->identifier());
            }
            $value = [$value];
        }

        $maxItems = isset($definition->options['max_items'])
            ? max(1, (int)$definition->options['max_items'])
            : 100;

        if (count($value) > $maxItems) {
            throw new FieldValidationException('Too many items for ' . $definition->identifier());
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_int($item) && $item > 0) {
                $ids[] = $item;
                continue;
            }
            if (is_string($item) && ctype_digit($item) && (int)$item > 0) {
                $ids[] = (int)$item;
                continue;
            }
            throw new FieldValidationException('Invalid id for ' . $definition->identifier());
        }

        return array_values(array_unique($ids));
    }

    private function id(mixed $value, FieldDefinition $definition): int
    {
        $ids = $this->ids([$value], $definition);
        return $ids[0];
    }

    private function json(mixed $value, FieldDefinition $definition): mixed
    {
        if (is_string($value)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new FieldValidationException('Invalid JSON for ' . $definition->identifier());
            }
        }

        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new FieldValidationException('Non-serializable JSON value for ' . $definition->identifier());
        }

        $maxBytes = isset($definition->options['max_bytes'])
            ? max(1, (int)$definition->options['max_bytes'])
            : 262144;
        if (strlen($encoded) > $maxBytes) {
            throw new FieldValidationException('JSON field exceeds size limit: ' . $definition->identifier());
        }

        return $value;
    }

    /** @return list<array<string,mixed>> */
    private function repeater(mixed $value, FieldDefinition $definition): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new FieldValidationException('Expected repeater list for ' . $definition->identifier());
        }

        $maxItems = isset($definition->options['max_items'])
            ? max(1, (int)$definition->options['max_items'])
            : 100;

        if (count($value) > $maxItems) {
            throw new FieldValidationException('Repeater exceeds item limit: ' . $definition->identifier());
        }

        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new FieldValidationException('Repeater rows must be objects for ' . $definition->identifier());
            }
        }

        $encoded = json_encode($value);
        $maxBytes = isset($definition->options['max_bytes'])
            ? max(1, (int)$definition->options['max_bytes'])
            : 524288;
        if (!is_string($encoded) || strlen($encoded) > $maxBytes) {
            throw new FieldValidationException('Repeater exceeds size limit: ' . $definition->identifier());
        }

        return array_values($value);
    }

    /** @return array<string,mixed> */
    private function group(mixed $value, FieldDefinition $definition): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new FieldValidationException('Expected group object for ' . $definition->identifier());
        }

        $encoded = json_encode($value);
        $maxBytes = isset($definition->options['max_bytes'])
            ? max(1, (int)$definition->options['max_bytes'])
            : 262144;
        if (!is_string($encoded) || strlen($encoded) > $maxBytes) {
            throw new FieldValidationException('Group exceeds size limit: ' . $definition->identifier());
        }

        return $value;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
