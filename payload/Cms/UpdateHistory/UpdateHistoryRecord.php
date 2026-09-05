<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdateHistory;

final readonly class UpdateHistoryRecord
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $id,
        public string $extensionId,
        public string $fromVersion,
        public int $fromVersionCode,
        public string $toVersion,
        public int $toVersionCode,
        public UpdateHistoryStatus $status,
        public ?string $recoveryPointId,
        public ?int $actorId,
        public float $occurredAt,
        public array $metadata = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'extension_id' => $this->extensionId,
            'from_version' => $this->fromVersion,
            'from_version_code' => $this->fromVersionCode,
            'to_version' => $this->toVersion,
            'to_version_code' => $this->toVersionCode,
            'status' => $this->status->value,
            'recovery_point_id' => $this->recoveryPointId,
            'actor_id' => $this->actorId,
            'occurred_at' => $this->occurredAt,
            'metadata' => $this->metadata,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['id'] ?? ''),
            (string)($data['extension_id'] ?? ''),
            (string)($data['from_version'] ?? ''),
            (int)($data['from_version_code'] ?? 0),
            (string)($data['to_version'] ?? ''),
            (int)($data['to_version_code'] ?? 0),
            UpdateHistoryStatus::from((string)($data['status'] ?? 'started')),
            isset($data['recovery_point_id']) ? (string)$data['recovery_point_id'] : null,
            isset($data['actor_id']) ? (int)$data['actor_id'] : null,
            (float)($data['occurred_at'] ?? 0),
            is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }
}
