<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class ExtensionAdminModuleResolver
{
    /** @return array{path:string,layout:string}|null */
    public function resolve(string $packageRoot, string $asset): ?array
    {
        if (preg_match('/^[a-z][a-z0-9._-]{0,80}\.mjs$/', $asset) !== 1) {
            return null;
        }

        $rootReal = realpath($packageRoot);
        if ($rootReal === false || !is_dir($rootReal) || is_link($packageRoot)) {
            return null;
        }

        $module = substr($asset, 0, -4);
        if ($module === '' || str_contains($module, '..')) {
            return null;
        }

        $candidates = [
            ['path' => rtrim($rootReal, '/\\') . '/admin/dist/' . $asset, 'layout' => 'canonical'],
            ['path' => rtrim($rootReal, '/\\') . '/admin/' . $asset, 'layout' => 'legacy'],
        ];

        foreach ($candidates as $candidate) {
            $fileReal = realpath($candidate['path']);
            if ($this->allowedFile($rootReal, $fileReal)) {
                return ['path' => $fileReal, 'layout' => $candidate['layout']];
            }
        }

        return null;
    }

    private function allowedFile(string $rootReal, string|false $fileReal): bool
    {
        if ($fileReal === false || !is_file($fileReal) || is_link($fileReal)) {
            return false;
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $rootReal), '/') . '/';
        $normalized = str_replace('\\', '/', $fileReal);

        return str_starts_with($normalized, $rootPrefix);
    }
}
