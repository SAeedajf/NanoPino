<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer\Filesystem;

use RuntimeException;

final class AtomicDirectorySwitcher
{
    public function activate(string $staging, string $destination, string $transactionId): DirectorySwitchReceipt
    {
        $staging = rtrim($staging, '/\\');
        $destination = rtrim($destination, '/\\');
        if (!is_dir($staging)) {
            throw new RuntimeException('Staging directory does not exist.');
        }
        if (dirname($staging) !== dirname($destination)) {
            throw new RuntimeException('Staging and destination must share the same parent for atomic rename semantics.');
        }
        $backup = null;
        $fresh = !file_exists($destination);
        if (!$fresh) {
            $backup = dirname($destination) . '/.cms-backup-' . basename($destination) . '-' . $transactionId;
            if (file_exists($backup)) {
                throw new RuntimeException('Backup path already exists: ' . $backup);
            }
            if (!rename($destination, $backup)) {
                throw new RuntimeException('Cannot move current destination to backup.');
            }
        }

        if (!rename($staging, $destination)) {
            if ($backup !== null && !file_exists($destination)) {
                @rename($backup, $destination);
            }
            throw new RuntimeException('Cannot atomically switch staging directory into destination.');
        }

        return new DirectorySwitchReceipt($destination, $staging, $backup, $fresh);
    }

    public function rollback(DirectorySwitchReceipt $receipt): void
    {
        if (file_exists($receipt->destination)) {
            $failed = dirname($receipt->destination) . '/.cms-failed-' . basename($receipt->destination) . '-' . bin2hex(random_bytes(4));
            if (!rename($receipt->destination, $failed)) {
                throw new RuntimeException('Cannot move failed destination aside during rollback.');
            }
            self::removeTree($failed);
        }
        if ($receipt->backup !== null) {
            if (!is_dir($receipt->backup) || !rename($receipt->backup, $receipt->destination)) {
                throw new RuntimeException('Cannot restore previous destination from backup.');
            }
        }
    }

    public function commit(DirectorySwitchReceipt $receipt): void
    {
        if ($receipt->backup !== null && file_exists($receipt->backup)) {
            self::removeTree($receipt->backup);
        }
    }

    private static function removeTree(string $path): void
    {
        if (!file_exists($path)) { return; }
        if (is_file($path) || is_link($path)) {
            if (!unlink($path)) { throw new RuntimeException('Cannot remove path: ' . $path); }
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $ok = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (!$ok) { throw new RuntimeException('Cannot remove rollback path: ' . $item->getPathname()); }
        }
        if (!rmdir($path)) { throw new RuntimeException('Cannot remove directory: ' . $path); }
    }
}
