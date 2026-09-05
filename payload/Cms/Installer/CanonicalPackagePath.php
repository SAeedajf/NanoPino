<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

use App\com_pinoox_cms\Cms\Exception\PackageFilePlanException;

final class CanonicalPackagePath
{
    public static function normalize(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new PackageFilePlanException('Package entry path is empty or contains NUL.');
        }

        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new PackageFilePlanException('Absolute package entry paths are forbidden: ' . $path);
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            throw new PackageFilePlanException('URI-like package entry paths are forbidden: ' . $path);
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new PackageFilePlanException('Control characters are forbidden in package paths.');
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new PackageFilePlanException('Path traversal is forbidden in package entries: ' . $path);
            }
            $parts[] = $part;
        }

        if ($parts === []) {
            throw new PackageFilePlanException('Package entry resolves to an empty path.');
        }

        $canonical = implode('/', $parts);
        if (strlen($canonical) > 1024) {
            throw new PackageFilePlanException('Package entry path exceeds safety limit.');
        }
        return $canonical;
    }
}
