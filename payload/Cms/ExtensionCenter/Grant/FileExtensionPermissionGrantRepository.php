<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Grant;

use RuntimeException;

final readonly class FileExtensionPermissionGrantRepository implements ExtensionPermissionGrantRepositoryInterface
{
    public function __construct(private string $directory) {}

    public function find(string $extensionId): ?ExtensionPermissionGrant
    {
        $file = $this->path($extensionId);
        if (!is_file($file)) return null;

        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? ExtensionPermissionGrant::fromArray($data) : null;
    }

    public function save(ExtensionPermissionGrant $grant): void
    {
        $this->ensureDirectory();
        $file = $this->path($grant->extensionId);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode(
            $grant->toArray(),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if (file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to persist extension permission grant.');
        }
    }

    public function delete(string $extensionId): void
    {
        $file = $this->path($extensionId);
        if (is_file($file) && !@unlink($file)) {
            throw new RuntimeException('Unable to delete extension permission grant.');
        }
    }

    public function all(): array
    {
        if (!is_dir($this->directory)) return [];
        $items = [];
        foreach (glob(rtrim($this->directory, '/\\') . '/*.json') ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) $items[] = ExtensionPermissionGrant::fromArray($data);
        }
        usort($items, static fn ($a, $b): int => $b->approvedAt <=> $a->approvedAt);
        return $items;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create extension grant directory.');
        }
    }

    private function path(string $extensionId): string
    {
        if ($extensionId === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,189}$/', $extensionId) !== 1) {
            throw new RuntimeException('Invalid extension grant id.');
        }

        return rtrim($this->directory, '/\\') . '/' . hash('sha256', $extensionId) . '.json';
    }
}
