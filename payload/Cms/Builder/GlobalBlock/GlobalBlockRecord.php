<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\GlobalBlock;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

final readonly class GlobalBlockRecord
{
    public function __construct(
        public int $id,
        public int $siteId,
        public string $name,
        public BlockDocument $document,
        public string $checksum,
        public int $version,
        public ?int $actorId,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
