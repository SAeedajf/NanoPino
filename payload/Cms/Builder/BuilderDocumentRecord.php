<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

final readonly class BuilderDocumentRecord
{
    public function __construct(
        public int $id,
        public BuilderTarget $target,
        public BuilderStatus $status,
        public BlockDocument $document,
        public string $checksum,
        public int $version,
        public ?int $actorId,
        public ?string $publishedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
