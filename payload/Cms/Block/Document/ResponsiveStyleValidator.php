<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Document;

final class ResponsiveStyleValidator
{
    private const STYLE_KEYS = [
        'color', 'backgroundColor', 'fontSize', 'fontWeight', 'lineHeight',
        'letterSpacing', 'margin', 'padding', 'width', 'height', 'minWidth',
        'maxWidth', 'minHeight', 'maxHeight', 'display', 'alignItems',
        'justifyContent', 'gap', 'gridTemplateColumns', 'textAlign',
        'borderRadius', 'borderWidth', 'borderColor', 'opacity',
    ];

    /** @param array<string,mixed> $styles */
    public function validateStyles(array $styles): bool
    {
        if (count($styles) > 64) return false;

        foreach ($styles as $key => $value) {
            if (!in_array((string)$key, self::STYLE_KEYS, true)) return false;
            if (!is_scalar($value) && $value !== null) return false;
            if (is_string($value)) {
                $lower = strtolower($value);
                if (
                    strlen($value) > 500
                    || str_contains($lower, 'url(')
                    || str_contains($lower, 'javascript:')
                    || str_contains($lower, '@import')
                    || str_contains($value, ';')
                    || str_contains($value, '{')
                    || str_contains($value, '}')
                    || str_contains($value, '<')
                    || str_contains($value, '>')
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string,mixed> $responsive */
    public function validateResponsive(array $responsive): bool
    {
        if (count($responsive) > 16) return false;

        foreach ($responsive as $breakpoint => $styles) {
            if (preg_match('/^[a-z][a-z0-9_-]{0,31}$/', (string)$breakpoint) !== 1) {
                return false;
            }
            if (!is_array($styles) || !$this->validateStyles($styles)) {
                return false;
            }
        }

        return true;
    }
}
