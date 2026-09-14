<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

final readonly class BuilderDocumentSummary
{
    public function __construct(
        public int $id,
        public BuilderTarget $target,
        public BuilderStatus $status,
        public string $checksum,
        public int $version,
        public ?int $actorId,
        public ?string $publishedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
