<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Manifest;

final class ManifestNormalizer
{
    /** @return list<string> */
    public static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '' && !in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return array<string,string> */
    public static function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $constraint) {
            if (!is_string($key) || !is_string($constraint)) {
                continue;
            }
            $key = trim($key);
            $constraint = trim($constraint);
            if ($key !== '' && $constraint !== '') {
                $map[$key] = $constraint;
            }
        }

        return $map;
    }

    /** @return list<ExtensionDependencyRule> */
    public static function dependencies(mixed $value, bool $optional = false): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rules = [];
        foreach ($value as $key => $definition) {
            if (is_int($key) && is_string($definition)) {
                $rules[] = new ExtensionDependencyRule(trim($definition), '*', $optional);
                continue;
            }

            if (!is_string($key)) {
                continue;
            }

            if (is_string($definition)) {
                $rules[] = new ExtensionDependencyRule(trim($key), trim($definition) ?: '*', $optional);
                continue;
            }

            if (is_array($definition)) {
                $constraint = is_string($definition['constraint'] ?? null)
                    ? trim($definition['constraint'])
                    : '*';
                $isOptional = $optional || (bool) ($definition['optional'] ?? false);
                $rules[] = new ExtensionDependencyRule(trim($key), $constraint ?: '*', $isOptional);
            }
        }

        return $rules;
    }
}
