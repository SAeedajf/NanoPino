<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

use InvalidArgumentException;

final class DesignSchemaValidator
{
    private const ROOT_KEYS = [
        'colors',
        'typography',
        'fontFamilies',
        'fontSizes',
        'spacing',
        'containers',
        'breakpoints',
        'grid',
        'radius',
        'shadows',
        'buttons',
        'forms',
        'links',
        'motion',
        'icons',
        'darkMode',
        'rtl',
        'accessibility',
    ];

    /** @param array<string,mixed> $raw */
    public function validate(array $raw): DesignDocument
    {
        $schema = (int)($raw['schema'] ?? 1);
        if ($schema !== 1) {
            throw new InvalidArgumentException('Unsupported design.json schema.');
        }

        $tokens = $raw['tokens'] ?? $raw;
        if (isset($tokens['schema'])) {
            unset($tokens['schema']);
        }

        if (!is_array($tokens)) {
            throw new InvalidArgumentException('design.json tokens must be an object.');
        }

        foreach (array_keys($tokens) as $key) {
            if (!in_array((string)$key, self::ROOT_KEYS, true)) {
                throw new InvalidArgumentException('Unknown design token root: ' . $key);
            }
        }

        $this->walk($tokens, 0, 0);

        return new DesignDocument($schema, $tokens);
    }

    /** @param array<mixed> $value */
    private function walk(array $value, int $depth, int $nodes): int
    {
        if ($depth > 10) {
            throw new InvalidArgumentException('Design token nesting exceeds limit.');
        }

        foreach ($value as $key => $item) {
            ++$nodes;
            if ($nodes > 5000) {
                throw new InvalidArgumentException('Design token document is too large.');
            }

            if (!is_int($key) && preg_match('/^[A-Za-z][A-Za-z0-9._-]{0,95}$/', (string)$key) !== 1) {
                throw new InvalidArgumentException('Invalid design token key.');
            }

            if (is_array($item)) {
                $nodes = $this->walk($item, $depth + 1, $nodes);
                continue;
            }

            if (!is_scalar($item) && $item !== null) {
                throw new InvalidArgumentException('Design token values must be scalar, null or nested objects.');
            }

            if (is_string($item)) {
                $length = function_exists('mb_strlen') ? mb_strlen($item) : strlen($item);
                if ($length > 1000) {
                    throw new InvalidArgumentException('Design token string exceeds length limit.');
                }
            }
        }

        return $nodes;
    }
}
