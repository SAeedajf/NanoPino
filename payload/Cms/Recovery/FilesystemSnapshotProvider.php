<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use RuntimeException;

final class FilesystemSnapshotProvider implements SnapshotProviderInterface
{
    public function __construct(
        private readonly string $sourcePath,
        private readonly string $snapshotRoot,
    ) {}

    public function id(): string { return 'filesystem'; }

    public function create(RecoveryPoint $point): array
    {
        $target = rtrim($this->snapshotRoot, '/\\') . '/' . $point->id . '/filesystem';
        $this->remove($target);
        if (!is_dir($this->sourcePath)) {
            return ['exists' => false, 'path' => $target, 'source' => $this->sourcePath];
        }

        $this->copyTree($this->sourcePath, $target);
        return [
            'exists' => true,
            'path' => $target,
            'source' => $this->sourcePath,
            'sha256' => $this->treeHash($target),
        ];
    }

    public function restore(RecoveryPoint $point, array $receipt): void
    {
        $source = (string)($receipt['source'] ?? $this->sourcePath);
        $snapshot = (string)($receipt['path'] ?? '');

        if (!($receipt['exists'] ?? false)) {
            $this->remove($source);
            return;
        }

        if ($snapshot === '' || !is_dir($snapshot)) {
            throw new RuntimeException('Filesystem snapshot is unavailable.');
        }

        $expected = (string)($receipt['sha256'] ?? '');
        if ($expected !== '' && !hash_equals($expected, $this->treeHash($snapshot))) {
            throw new RuntimeException('Filesystem snapshot integrity check failed.');
        }

        $parent = dirname($source);
        if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
            throw new RuntimeException('Unable to create restore parent directory.');
        }

        $restore = $parent . '/.cms-restore-' . basename($source) . '-' . $point->id;
        $this->remove($restore);
        $this->copyTree($snapshot, $restore);

        $failedCurrent = null;
        if (file_exists($source)) {
            $failedCurrent = $parent . '/.cms-failed-' . basename($source) . '-' . $point->id;
            $this->remove($failedCurrent);
            if (!@rename($source, $failedCurrent)) {
                $this->remove($restore);
                throw new RuntimeException('Unable to move current tree before restore.');
            }
        }

        if (!@rename($restore, $source)) {
            if ($failedCurrent !== null && is_dir($failedCurrent)) {
                @rename($failedCurrent, $source);
            }
            throw new RuntimeException('Unable to atomically activate restored filesystem snapshot.');
        }

        if ($failedCurrent !== null) {
            $this->remove($failedCurrent);
        }
    }

    public function delete(RecoveryPoint $point, array $receipt): void
    {
        $path = (string)($receipt['path'] ?? '');
        if ($path !== '') {
            $this->remove(dirname($path));
        }
    }

    private function copyTree(string $source, string $target): void
    {
        if (is_link($source)) {
            throw new RuntimeException('Snapshot source may not be a symbolic link.');
        }
        if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
            throw new RuntimeException('Unable to create snapshot directory.');
        }

        foreach (scandir($source) ?: [] as $name) {
            if ($name === '.' || $name === '..') { continue; }
            $from = $source . '/' . $name;
            $to = $target . '/' . $name;

            if (is_link($from)) {
                throw new RuntimeException('Symbolic links are not supported in recovery snapshots.');
            }
            if (is_dir($from)) {
                $this->copyTree($from, $to);
            } elseif (!copy($from, $to)) {
                throw new RuntimeException('Unable to snapshot file: ' . $from);
            }
        }
    }

    private function treeHash(string $root): string
    {
        $rows = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symbolic link found in snapshot.');
            }
            if (!$file->isFile()) { continue; }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $rows[$relative] = hash_file('sha256', $file->getPathname()) ?: '';
        }
        ksort($rows);
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '[]');
    }

    private function remove(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) { return; }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') { continue; }
            $this->remove($path . '/' . $name);
        }
        @rmdir($path);
    }
}
