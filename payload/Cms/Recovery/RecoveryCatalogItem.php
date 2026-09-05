<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final readonly class RecoveryCatalogItem
{
    public function __construct(
        public RecoveryPoint $point,
        public bool $restorable,
        public bool $safeModeTarget,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->point->id,
            'extensionId' => $this->point->extensionId,
            'operation' => $this->point->operation,
            'createdAt' => $this->point->createdAt,
            'status' => $this->point->status->value,
            'restorable' => $this->restorable,
            'safeModeTarget' => $this->safeModeTarget,
            'metadata' => $this->point->metadata,
        ];
    }
}
