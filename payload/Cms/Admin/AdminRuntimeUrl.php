<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use Pinoox\Portal\App\App as PinooxApp;

/**
 * Builds same-origin CMS Admin module URLs inside the active Pinoox app mount.
 *
 * Pinoox web routes are mounted below App::pathRoute() (for example /qwe).
 * Root-absolute /__cms/* URLs therefore escape the CMS app and are not routable
 * when the app is mounted below a non-root path.
 */
final class AdminRuntimeUrl
{
    public static function currentMountPath(): string
    {
        try {
            return self::normalizeMountPath((string) (PinooxApp::pathRoute() ?? '/'));
        } catch (\Throwable) {
            return '/';
        }
    }

    public static function normalizeMountPath(string $mountPath): string
    {
        $mountPath = trim(str_replace('\\', '/', $mountPath));
        if ($mountPath === '' || $mountPath === '/') {
            return '/';
        }

        if (
            str_contains($mountPath, "\0")
            || str_contains($mountPath, '?')
            || str_contains($mountPath, '#')
            || preg_match('#^[a-z][a-z0-9+.-]*://#i', $mountPath) === 1
        ) {
            throw new \InvalidArgumentException('Invalid Pinoox app mount path.');
        }

        $segments = array_values(array_filter(explode('/', $mountPath), static fn (string $part): bool => $part !== ''));
        if ($segments === [] || in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw new \InvalidArgumentException('Invalid Pinoox app mount path.');
        }

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z0-9._~-]+$/', $segment) !== 1) {
                throw new \InvalidArgumentException('Invalid Pinoox app mount segment.');
            }
        }

        return '/' . implode('/', $segments);
    }

    public static function controlPlaneModule(string $asset, ?string $mountPath = null): string
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,60}\.mjs$/', $asset) !== 1) {
            throw new \InvalidArgumentException('Invalid CMS Admin runtime module asset.');
        }

        return self::rebase('/__cms/admin/modules/' . $asset, $mountPath);
    }

    public static function extensionModule(string $package, string $module, ?string $mountPath = null): string
    {
        if (preg_match('/^com_[a-z0-9][a-z0-9_]*$/', $package) !== 1) {
            throw new \InvalidArgumentException('Invalid Admin asset package.');
        }
        if (preg_match('/^[a-z][a-z0-9._-]{0,80}$/', $module) !== 1) {
            throw new \InvalidArgumentException('Invalid Admin asset module.');
        }

        return self::rebase('/__cms/extensions/' . $package . '/admin/' . $module . '.mjs', $mountPath);
    }

    /**
     * Prefix an app-local absolute path with the active Pinoox mount.
     *
     * API routes registered through AppRegister::api() are loaded into the
     * current app Router, so a CMS mounted at /qwe exposes /qwe/api/... .
     */
    public static function appPath(string $path, ?string $mountPath = null): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if (
            $path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, "\0")
            || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1
        ) {
            throw new \InvalidArgumentException('Invalid app-local path.');
        }

        [$pathname, $query] = array_pad(explode('?', $path, 2), 2, null);
        $segments = array_values(array_filter(explode('/', $pathname), static fn (string $part): bool => $part !== ''));
        if ($segments === [] || in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw new \InvalidArgumentException('Invalid app-local path segments.');
        }

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z0-9._~{}-]+$/', $segment) !== 1) {
                throw new \InvalidArgumentException('Invalid app-local path segment.');
            }
        }

        $mount = self::normalizeMountPath($mountPath ?? self::currentMountPath());
        $normalizedPath = '/' . implode('/', $segments);
        if ($mount !== '/' && $normalizedPath !== $mount && !str_starts_with($normalizedPath, $mount . '/')) {
            $normalizedPath = $mount . $normalizedPath;
        }

        return $query !== null && $query !== '' ? $normalizedPath . '?' . $query : $normalizedPath;
    }

    public static function apiBase(?string $mountPath = null): string
    {
        return self::appPath('/api/v1/cms', $mountPath);
    }

    /**
     * Rebase historical root /__cms/* URLs into the active Pinoox app mount.
     * Already-mounted URLs are returned unchanged.
     */
    public static function rebase(string $moduleUrl, ?string $mountPath = null): string
    {
        $moduleUrl = trim(str_replace('\\', '/', $moduleUrl));
        if (
            $moduleUrl === ''
            || !str_starts_with($moduleUrl, '/')
            || str_starts_with($moduleUrl, '//')
            || str_contains($moduleUrl, "\0")
            || preg_match('#^[a-z][a-z0-9+.-]*://#i', $moduleUrl) === 1
        ) {
            throw new \InvalidArgumentException('Invalid same-origin Admin module URL.');
        }

        [$path, $query] = array_pad(explode('?', $moduleUrl, 2), 2, null);
        $segments = array_values(array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw new \InvalidArgumentException('Admin module path traversal rejected.');
        }

        $mount = self::normalizeMountPath($mountPath ?? self::currentMountPath());
        $normalizedPath = '/' . implode('/', $segments);
        if ($normalizedPath === '/') {
            throw new \InvalidArgumentException('Invalid Admin module URL.');
        }

        if ($mount !== '/') {
            if ($normalizedPath === $mount || str_starts_with($normalizedPath, $mount . '/')) {
                $rebased = $normalizedPath;
            } elseif (str_starts_with($normalizedPath, '/__cms/')) {
                $rebased = $mount . $normalizedPath;
            } else {
                $rebased = $normalizedPath;
            }
        } else {
            $rebased = $normalizedPath;
        }

        return $query !== null && $query !== '' ? $rebased . '?' . $query : $rebased;
    }
}
