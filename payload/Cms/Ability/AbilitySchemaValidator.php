<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

final class AbilitySchemaValidator
{
    /** @param array<string,mixed> $schema */
    public function validate(array $schema, mixed $value): void
    {
        if ($schema === []) {
            return;
        }

        $this->validateNode($schema, $value, '$');
    }

    /** @param array<string,mixed> $schema */
    private function validateNode(array $schema, mixed $value, string $path): void
    {
        if (isset($schema['type'])) {
            $this->assertType((string)$schema['type'], $value, $path);
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            throw new AbilitySchemaValidationException($path . ' is not one of the allowed values.');
        }

        if (is_string($value)) {
            if (isset($schema['minLength']) && (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) < (int)$schema['minLength']) {
                throw new AbilitySchemaValidationException($path . ' is shorter than minLength.');
            }
            if (isset($schema['maxLength']) && (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > (int)$schema['maxLength']) {
                throw new AbilitySchemaValidationException($path . ' exceeds maxLength.');
            }
        }

        if (is_array($value) && ($schema['type'] ?? null) === 'object') {
            $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
            foreach ($required as $requiredKey) {
                if (!array_key_exists((string)$requiredKey, $value)) {
                    throw new AbilitySchemaValidationException(
                        $path . '.' . $requiredKey . ' is required.'
                    );
                }
            }

            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            foreach ($properties as $key => $childSchema) {
                if (!array_key_exists((string)$key, $value) || !is_array($childSchema)) {
                    continue;
                }
                $this->validateNode($childSchema, $value[(string)$key], $path . '.' . $key);
            }

            if (($schema['additionalProperties'] ?? true) === false) {
                $unknown = array_diff(array_keys($value), array_keys($properties));
                if ($unknown !== []) {
                    throw new AbilitySchemaValidationException(
                        $path . ' contains unknown properties: ' . implode(', ', $unknown)
                    );
                }
            }
        }

        if (is_array($value) && ($schema['type'] ?? null) === 'array' && is_array($schema['items'] ?? null)) {
            foreach ($value as $index => $item) {
                $this->validateNode($schema['items'], $item, $path . '[' . $index . ']');
            }
        }
    }

    private function assertType(string $type, mixed $value, string $path): void
    {
        $valid = match ($type) {
            'object' => is_array($value) && !array_is_list($value),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => false,
        };

        if (!$valid) {
            throw new AbilitySchemaValidationException(
                sprintf('%s must be of type %s.', $path, $type)
            );
        }
    }
}
