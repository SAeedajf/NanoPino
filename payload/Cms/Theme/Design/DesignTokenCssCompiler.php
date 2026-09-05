<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

use InvalidArgumentException;

final class DesignTokenCssCompiler
{
    /**
     * Emits only scalar leaf tokens as CSS custom properties.
     * Complex component structures remain available to renderers via the token document.
     */
    public function compile(ResolvedDesign $design, string $selector = ':root'): string
    {
        if (!$this->safeSelector($selector)) {
            throw new InvalidArgumentException('Unsafe design token CSS selector.');
        }

        $flat = [];
        $this->flatten([], $design->tokens, $flat);
        ksort($flat);

        $lines = [];
        foreach ($flat as $path => $value) {
            if ($value === null || is_bool($value) || is_array($value)) {
                continue;
            }
            $cssValue = $this->safeValue((string)$value);
            if ($cssValue === null) {
                continue;
            }
            $suffix = trim(strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '-', $path) ?? ''), '-');
            if ($suffix === '') {
                continue;
            }
            $name = '--cms-' . $suffix;
            if (strlen($name) > 180) {
                continue;
            }
            $lines[] = '  ' . $name . ': ' . $cssValue . ';';
        }

        return $selector . " {\n" . implode("\n", $lines) . "\n}\n";
    }

    /**
     * @param list<string> $prefix
     * @param array<mixed> $value
     * @param array<string,mixed> $flat
     */
    private function flatten(array $prefix, array $value, array &$flat): void
    {
        foreach ($value as $key => $item) {
            $path = [...$prefix, (string)$key];
            if (is_array($item) && !array_is_list($item)) {
                $this->flatten($path, $item, $flat);
            } elseif (!is_array($item)) {
                $flat[implode('-', $path)] = $item;
            }
        }
    }

    private function safeSelector(string $selector): bool
    {
        return preg_match('/^(?::root|\.cms-theme(?:-[a-z0-9_-]+)?)$/', $selector) === 1;
    }

    private function safeValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 500) {
            return null;
        }

        $lower = strtolower($value);
        foreach ([';', '{', '}', '<', '>', '\\', 'url(', '@import', 'expression(', 'javascript:'] as $blocked) {
            if (str_contains($lower, $blocked)) {
                return null;
            }
        }

        return $value;
    }
}
