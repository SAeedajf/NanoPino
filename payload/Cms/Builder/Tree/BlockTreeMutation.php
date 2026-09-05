<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Tree;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;

final readonly class BlockTreeMutation
{
    public function __construct(
        public BlockDocument $document,
        public BlockNode $node,
        public ?string $parentId,
        public int $index,
        public ?string $slot,
    ) {}
}
