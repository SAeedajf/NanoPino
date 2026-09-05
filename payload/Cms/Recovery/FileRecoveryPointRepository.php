<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use RuntimeException;

final class FileRecoveryPointRepository implements RecoveryPointRepositoryInterface
{
    public function __construct(private readonly string $directory) {}

    public function save(RecoveryPoint $point): void
    {
        $this->ensureDirectory();
        $path = $this->path($point->id);
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($point->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('Unable to persist recovery point.');
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to atomically publish recovery point.');
        }
    }

    public function find(string $id): ?RecoveryPoint
    {
        $path = $this->path($id);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? RecoveryPoint::fromArray($data) : null;
    }

    public function forExtension(string $extensionId): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $points = [];
        foreach (glob(rtrim($this->directory, '/\\') . '/*.json') ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data) || (string)($data['extension_id'] ?? '') !== $extensionId) {
                continue;
            }
            $points[] = RecoveryPoint::fromArray($data);
        }

        usort($points, static fn (RecoveryPoint $a, RecoveryPoint $b): int => $b->createdAt <=> $a->createdAt);
        return $points;
    }

    /** @return list<RecoveryPoint> */
    public function all(int $limit = 200): array
    {
        if (!is_dir($this->directory)) return [];
        $points = [];
        foreach (glob(rtrim($this->directory, '/\\') . '/*.json') ?: [] as $file) {
            $data = json_decode((string)@file_get_contents($file), true);
            if (!is_array($data)) continue;
            try { $points[] = RecoveryPoint::fromArray($data); } catch (\Throwable) {}
        }
        usort($points, static fn(RecoveryPoint $a, RecoveryPoint $b): int => $b->createdAt <=> $a->createdAt);
        return array_slice($points, 0, max(1, min(1000, $limit)));
    }

    public function delete(string $id): void
    {
        $path = $this->path($id);
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('Unable to delete recovery point metadata.');
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create recovery point directory.');
        }
    }

    private function path(string $id): string
    {
        if ($id === '' || preg_match('/^[A-Za-z0-9._-]+$/', $id) !== 1) {
            throw new RuntimeException('Invalid recovery point id.');
        }
        return rtrim($this->directory, '/\\') . '/' . $id . '.json';
    }
}
