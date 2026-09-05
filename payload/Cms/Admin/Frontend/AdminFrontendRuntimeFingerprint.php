<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin\Frontend;

/**
 * Fingerprint directly-served Admin runtime ES modules independently from Vite.
 * Runtime modules are not bundle inputs and must not force a fake Vite rebuild.
 */
final class AdminFrontendRuntimeFingerprint
{
    /** @return array{fingerprint:string,files:int,paths:list<string>} */
    public function calculate(string $themePath): array
    {
        $root = realpath($themePath);
        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException('Admin theme root is unavailable.');
        }

        $root = str_replace('\\', '/', $root);
        $base = $root . '/runtime';
        $paths = [];

        if (is_dir($base)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $path = str_replace('\\', '/', $file->getPathname());
                if (is_link($path)) {
                    throw new \RuntimeException('Admin runtime fingerprint rejects symlinks.');
                }
                if (!str_starts_with($path, $root . '/')) {
                    throw new \RuntimeException('Admin runtime file escaped theme root.');
                }
                $relative = substr($path, strlen($root) + 1);
                if (!$this->safeRelative($relative)) {
                    throw new \RuntimeException('Unsafe Admin runtime fingerprint path.');
                }
                $paths[] = $relative;
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);
        if ($paths === []) {
            throw new \RuntimeException('Admin runtime module tree is empty.');
        }

        $context = hash_init('sha256');
        foreach ($paths as $relative) {
            $fileHash = hash_file('sha256', $root . '/' . $relative);
            if (!is_string($fileHash)) {
                throw new \RuntimeException('Unable to hash Admin runtime module.');
            }
            hash_update($context, $relative . "\0" . $fileHash . "\n");
        }

        return [
            'fingerprint' => hash_final($context),
            'files' => count($paths),
            'paths' => $paths,
        ];
    }

    private function safeRelative(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized)) {
            return false;
        }
        return !in_array('..', explode('/', $normalized), true);
    }
}
