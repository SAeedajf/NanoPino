<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

use RuntimeException;

final readonly class FileExtensionOperationLock implements ExtensionOperationLockInterface
{
    public function __construct(private string $directory) {}

    public function synchronized(string $extensionId, callable $operation): mixed
    {
        $dir = rtrim($this->directory, '/\\');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create extension operation lock directory.');
        }

        $key = hash('sha256', $extensionId);
        $handle = fopen($dir . '/' . $key . '.lock', 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open extension operation lock.');
        }

        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw new ExtensionOperationConflictException(
                    'Another extension operation is already running.'
                );
            }

            return $operation();
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
}
