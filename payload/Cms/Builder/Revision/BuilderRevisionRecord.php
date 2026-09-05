<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Revision;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

final readonly class BuilderRevisionRecord
{
    public function __construct(
        public int $id,
        public int $builderId,
        public BuilderRevisionKind $kind,
        public BlockDocument $document,
        public string $checksum,
        public ?int $actorId,
        public ?int $sourceRevisionId,
        public string $createdAt,
    ) {}
}
