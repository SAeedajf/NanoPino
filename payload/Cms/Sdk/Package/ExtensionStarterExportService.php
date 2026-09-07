<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Package;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;

final class ExtensionStarterExportService
{
    private const MAX_SOURCE_BYTES = 1048576;
    private const MAX_ARCHIVE_BYTES = 2097152;

    public function __construct(
        private readonly string $storageRoot,
        private readonly ExtensionScaffoldGenerator $generator = new ExtensionScaffoldGenerator(),
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function generate(ExtensionPackageBlueprint $spec): array
    {
        if ($spec->type === ExtensionType::CoreModule) {
            throw new \InvalidArgumentException('Core Module starters cannot be generated from Admin.');
        }

        $base = rtrim($this->storageRoot, '/\\') . '/sdk/starters';
        $this->ensureDirectory($base);
        $workspace = $base . '/starter-' . bin2hex(random_bytes(12));
        $this->ensureDirectory($workspace);

        try {
            $result = $this->generator->generate($spec, $workspace);
            $files = [];
            $total = 0;

            foreach ($result->files as $relative) {
                $relative = $this->relativePath($relative);
                $file = $workspace . '/' . $relative;
                if (!is_file($file) || is_link($file)) {
                    throw new \RuntimeException('Generated starter file is unavailable.');
                }

                $size = filesize($file);
                if (!is_int($size) || $size < 0) {
                    throw new \RuntimeException('Generated starter file size is unavailable.');
                }
                $total += $size;
                if ($total > self::MAX_SOURCE_BYTES) {
                    throw new \RuntimeException('Generated starter source exceeds the export size limit.');
                }

                $contents = file_get_contents($file);
                $sha = hash_file('sha256', $file);
                if (!is_string($contents) || !is_string($sha)) {
                    throw new \RuntimeException('Generated starter file could not be read.');
                }

                $files[] = [
                    'path' => $relative,
                    'bytes' => $size,
                    'sha256' => $sha,
                    'content' => $contents,
                ];
            }

            return [
                'starter' => [
                    'package' => $spec->package,
                    'name' => $spec->name,
                    'type' => $spec->type->value,
                    'version' => $spec->version,
                    'publisher' => $spec->publisher,
                ],
                'manifest' => $result->manifest,
                'files' => $files,
                'archive' => $this->archive($workspace, $result->files, $spec->package),
            ];
        } finally {
            $this->removeTree($workspace);
        }
    }

    /** @param list<string> $files @return array<string,mixed>|null */
    private function archive(string $workspace, array $files, string $package): ?array
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }

        $zipPath = $workspace . '/starter.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            return null;
        }

        try {
            foreach ($files as $relative) {
                $relative = $this->relativePath($relative);
                $source = $workspace . '/' . $relative;
                if (!is_file($source) || is_link($source) || !$zip->addFile($source, $relative)) {
                    throw new \RuntimeException('Unable to add a generated file to the starter archive.');
                }
            }
        } finally {
            $zip->close();
        }

        $size = filesize($zipPath);
        if (!is_int($size) || $size < 0 || $size > self::MAX_ARCHIVE_BYTES) {
            return null;
        }

        $bytes = file_get_contents($zipPath);
        $sha = hash_file('sha256', $zipPath);
        if (!is_string($bytes) || !is_string($sha)) {
            return null;
        }

        return [
            'filename' => $package . '-starter.zip',
            'mime' => 'application/zip',
            'encoding' => 'base64',
            'bytes' => $size,
            'sha256' => $sha,
            'content' => base64_encode($bytes),
        ];
    }

    private function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (
            $path === ''
            || str_starts_with($path, '/')
            || str_contains($path, '../')
            || str_contains($path, '/..')
            || str_contains($path, "\0")
            || preg_match('/^[A-Za-z0-9._\/-]+$/', $path) !== 1
        ) {
            throw new \RuntimeException('Generated starter contains an invalid relative path.');
        }
        return $path;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create SDK starter workspace.');
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $name = $item->getPathname();
            if ($item->isLink() || $item->isFile()) {
                @unlink($name);
            } elseif ($item->isDir()) {
                @rmdir($name);
            }
        }
        @rmdir($path);
    }
}
