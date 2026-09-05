<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final class RecoveryPoint
{
    /**
     * @param array<string,mixed> $metadata
     * @param array<string,array<string,mixed>> $providerReceipts
     */
    public function __construct(
        public readonly string $id,
        public readonly string $extensionId,
        public readonly string $operation,
        public readonly float $createdAt,
        public RecoveryPointStatus $status = RecoveryPointStatus::Creating,
        public array $metadata = [],
        public array $providerReceipts = [],
        public ?string $error = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'extension_id' => $this->extensionId,
            'operation' => $this->operation,
            'created_at' => $this->createdAt,
            'status' => $this->status->value,
            'metadata' => $this->metadata,
            'provider_receipts' => $this->providerReceipts,
            'error' => $this->error,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['id'] ?? ''),
            (string)($data['extension_id'] ?? ''),
            (string)($data['operation'] ?? ''),
            (float)($data['created_at'] ?? 0),
            RecoveryPointStatus::from((string)($data['status'] ?? 'creating')),
            is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            is_array($data['provider_receipts'] ?? null) ? $data['provider_receipts'] : [],
            isset($data['error']) ? (string)$data['error'] : null,
        );
    }
}
