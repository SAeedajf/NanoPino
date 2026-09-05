<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

use JsonException;

final class SettingCodec
{
    public static function coerce(SettingType $type, mixed $value): mixed
    {
        return match ($type) {
            SettingType::String => self::string($value),
            SettingType::Integer => self::integer($value),
            SettingType::Float => self::float($value),
            SettingType::Boolean => self::boolean($value),
            SettingType::Json => self::jsonValue($value),
            SettingType::StringList => self::stringList($value),
        };
    }

    public static function encode(SettingType $type, mixed $value): string
    {
        $value = self::coerce($type, $value);

        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public static function decode(SettingType $type, string $encoded): mixed
    {
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        return self::coerce($type, $decoded);
    }

    private static function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }

        throw new SettingValidationException('Expected string setting value.');
    }

    private static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int)$value;
        }

        throw new SettingValidationException('Expected integer setting value.');
    }

    private static function float(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float)$value;
        }

        throw new SettingValidationException('Expected numeric setting value.');
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false', 'off', 'no'], true)) {
            return false;
        }

        throw new SettingValidationException('Expected boolean setting value.');
    }

    private static function jsonValue(mixed $value): mixed
    {
        if (is_string($value)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new SettingValidationException('Invalid JSON setting value.', previous: $e);
            }
        }

        json_encode($value, JSON_THROW_ON_ERROR);
        return $value;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new SettingValidationException('Expected string-list setting value.');
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new SettingValidationException('String-list setting contains a non-string value.');
            }
            $result[] = $item;
        }

        return array_values($result);
    }
}
