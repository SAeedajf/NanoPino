<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use RuntimeException;

final class ThemeFileReader
{
    public function __construct(private readonly int $maxBytes = 1_048_576) {}

    public function read(string $themeRoot, string $relativePath): ?string
    {
        $file = $this->resolve($themeRoot, $relativePath);
        if (!is_file($file)) {
            return null;
        }

        $size = filesize($file);
        if (!is_int($size) || $size < 0 || $size > $this->maxBytes) {
            throw new RuntimeException('Theme file exceeds allowed size.');
        }

        $content = file_get_contents($file);
        if (!is_string($content)) {
            throw new RuntimeException('Theme file could not be read.');
        }

        return $content;
    }

    /** @return array<string,mixed>|null */
    public function json(string $themeRoot, string $relativePath): ?array
    {
        $content = $this->read($themeRoot, $relativePath);
        if ($content === null) {
            return null;
        }

        $decoded = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Theme JSON root must be an object.');
        }
        return $decoded;
    }

    /** @return list<string> */
    public function jsonFiles(string $themeRoot, string $relativeDirectory): array
    {
        $dir = $this->resolve($themeRoot, $relativeDirectory);
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'json') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (!is_file($path) || is_link($path)) {
                continue;
            }

            $real = realpath($path);
            $rootReal = realpath($themeRoot);
            if ($real === false || $rootReal === false) {
                continue;
            }

            $normalizedReal = str_replace('\\', '/', $real);
            $normalizedRoot = rtrim(str_replace('\\', '/', $rootReal), '/');
            if (!str_starts_with($normalizedReal, $normalizedRoot . '/')) {
                continue;
            }

            $size = filesize($real);
            if (!is_int($size) || $size < 0 || $size > $this->maxBytes) {
                throw new RuntimeException('Theme JSON file exceeds allowed size.');
            }

            $files[] = $real;
        }

        sort($files);
        return $files;
    }

    public function resolve(string $themeRoot, string $relativePath): string
    {
        $root = rtrim(str_replace('\\', '/', $themeRoot), '/');
        $relative = trim(str_replace('\\', '/', $relativePath), '/');

        if ($root === '' || $relative === '') {
            throw new RuntimeException('Theme path cannot be empty.');
        }

        if (
            str_contains($relative, "\0")
            || str_starts_with($relative, '/')
            || preg_match('/^[A-Za-z]:/', $relative) === 1
            || in_array('..', explode('/', $relative), true)
        ) {
            throw new RuntimeException('Unsafe theme file path.');
        }

        $rootReal = realpath($root);
        if ($rootReal === false || !is_dir($rootReal)) {
            throw new RuntimeException('Theme root does not exist.');
        }

        $candidate = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $parent = realpath(dirname($candidate));
        if ($parent !== false) {
            $normalizedRoot = rtrim(str_replace('\\', '/', $rootReal), '/');
            $normalizedParent = rtrim(str_replace('\\', '/', $parent), '/');
            if ($normalizedParent !== $normalizedRoot && !str_starts_with($normalizedParent . '/', $normalizedRoot . '/')) {
                throw new RuntimeException('Theme path escapes theme root.');
            }
        }

        return $candidate;
    }
}
