<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

use RuntimeException;

final readonly class FileExtensionOperationRepository implements ExtensionOperationRepositoryInterface
{
    public function __construct(private string $directory) {}

    public function save(ExtensionOperationRecord $operation): void
    {
        $dir = $this->dir();
        $file = $dir . '/' . $this->safeId($operation->id) . '.json';
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));

        $data = [
            'id' => $operation->id,
            'type' => $operation->type->value,
            'extension_id' => $operation->extensionId,
            'status' => $operation->status->value,
            'created_at' => $operation->createdAt,
            'started_at' => $operation->startedAt,
            'finished_at' => $operation->finishedAt,
            'recovery_point_id' => $operation->recoveryPointId,
            'internal_error' => $operation->internalError,
            'steps' => array_map(
                static fn (ExtensionOperationStep $step): array => $step->toArray(),
                $operation->steps,
            ),
        ];

        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (
            file_put_contents($tmp, $json, LOCK_EX) === false
            || !@rename($tmp, $file)
        ) {
            @unlink($tmp);
            throw new RuntimeException('Unable to persist extension operation journal.');
        }
    }

    public function find(string $id): ?ExtensionOperationRecord
    {
        $file = $this->dir() . '/' . $this->safeId($id) . '.json';
        if (!is_file($file)) return null;

        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $this->hydrate($data) : null;
    }

    public function forExtension(string $extensionId, int $limit = 50): array
    {
        $items = [];
        foreach (glob($this->dir() . '/*.json') ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data) || (string)($data['extension_id'] ?? '') !== $extensionId) {
                continue;
            }
            $items[] = $this->hydrate($data);
        }

        usort($items, static fn (ExtensionOperationRecord $a, ExtensionOperationRecord $b): int =>
            $b->createdAt <=> $a->createdAt
        );

        return array_slice($items, 0, max(1, min(200, $limit)));
    }

    public function activeForExtension(string $extensionId): ?ExtensionOperationRecord
    {
        foreach ($this->forExtension($extensionId, 200) as $item) {
            if (!$item->status->terminal()) return $item;
        }
        return null;
    }

    private function hydrate(array $data): ExtensionOperationRecord
    {
        $record = new ExtensionOperationRecord(
            (string)$data['id'],
            ExtensionOperationType::from((string)$data['type']),
            (string)$data['extension_id'],
            ExtensionOperationStatus::from((string)$data['status']),
            (float)$data['created_at'],
            isset($data['started_at']) ? (float)$data['started_at'] : null,
            isset($data['finished_at']) ? (float)$data['finished_at'] : null,
            isset($data['recovery_point_id']) ? (string)$data['recovery_point_id'] : null,
            isset($data['internal_error']) ? (string)$data['internal_error'] : null,
        );

        foreach (($data['steps'] ?? []) as $step) {
            if (!is_array($step)) continue;
            $record->steps[] = new ExtensionOperationStep(
                (string)($step['step'] ?? ''),
                (string)($step['status'] ?? ''),
                (string)($step['message'] ?? ''),
                (float)($step['occurred_at'] ?? 0),
            );
        }

        return $record;
    }

    private function dir(): string
    {
        $dir = rtrim($this->directory, '/\\');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create extension operation journal directory.');
        }
        return $dir;
    }

    private function safeId(string $id): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/', $id) !== 1) {
            throw new RuntimeException('Invalid extension operation ID.');
        }
        return $id;
    }
}
