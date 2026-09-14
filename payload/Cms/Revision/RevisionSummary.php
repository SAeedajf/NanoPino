<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

final readonly class RevisionSummary
{
    public function __construct(
        public int $id,
        public RevisionKind $kind,
        public string $checksum,
        public ?int $actorId,
        public ?int $sourceRevisionId,
        public string $createdAt,
    ) {}
}
