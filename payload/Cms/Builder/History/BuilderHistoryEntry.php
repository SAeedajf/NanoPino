<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\History;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

final readonly class BuilderHistoryEntry
{
    public function __construct(
        public string $command,
        public BlockDocument $before,
        public BlockDocument $after,
        public string $beforeChecksum,
        public string $afterChecksum,
    ) {}
}
