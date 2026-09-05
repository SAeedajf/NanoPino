<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use InvalidArgumentException;

final class CmsThemeProfileFactory
{
    /** @param array<string,mixed> $raw */
    public function fromNativeMeta(array $raw): CmsThemeProfile
    {
        $cms = $raw['cms'] ?? [];
        if (!is_array($cms)) {
            throw new InvalidArgumentException('Theme cms profile must be an object.');
        }

        $schema = (int)($cms['schema'] ?? 1);
        if ($schema !== 1) {
            throw new InvalidArgumentException('Unsupported CMS theme profile schema.');
        }

        // Canonical authoring shape is cms.theme. During 0.x we still accept
        // the earlier flat cms paths/features shape for migration compatibility.
        $theme = is_array($cms['theme'] ?? null) ? $cms['theme'] : $cms;

        $paths = is_array($theme['paths'] ?? null) ? $theme['paths'] : [];
        $extensions = is_array($theme['template_extensions'] ?? null)
            ? array_values(array_filter(array_map('strval', $theme['template_extensions'])))
            : ['twig', 'php', 'html'];

        foreach ($extensions as $extension) {
            if (preg_match('/^[a-z0-9]{1,12}$/', $extension) !== 1) {
                throw new InvalidArgumentException('Invalid template extension.');
            }
        }

        return new CmsThemeProfile(
            schemaVersion: $schema,
            minimumCms: $this->nullableString($theme['minimum_cms'] ?? null),
            maximumCms: $this->nullableString($theme['maximum_cms'] ?? null),
            designFile: $this->safeRelative((string)($paths['design'] ?? 'design.json')),
            templateDirectory: $this->safeRelative((string)($paths['templates'] ?? 'templates')),
            partDirectory: $this->safeRelative((string)($paths['parts'] ?? 'parts')),
            patternDirectory: $this->safeRelative((string)($paths['patterns'] ?? 'patterns')),
            variationDirectory: $this->safeRelative((string)($paths['variations'] ?? 'styles/variations')),
            templateExtensions: $extensions,
            features: is_array($theme['features'] ?? null) ? $theme['features'] : [],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        return trim((string)$value);
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if (
            $path === ''
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path) === 1
            || in_array('..', explode('/', $path), true)
        ) {
            throw new InvalidArgumentException('Unsafe theme relative path.');
        }
        return $path;
    }
}
