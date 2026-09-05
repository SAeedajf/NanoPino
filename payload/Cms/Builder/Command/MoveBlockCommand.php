<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Command;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;

final readonly class MoveBlockCommand implements BuilderCommandInterface
{
    public function __construct(
        private BlockTreeEditor $tree,
        public string $blockId,
        public ?string $parentId,
        public int $index,
        public ?string $slot = null,
    ) {}

    public function name(): string { return 'move'; }

    public function apply(BlockDocument $document): BlockDocument
    {
        return $this->tree->move($document, $this->blockId, $this->parentId, $this->index, $this->slot);
    }
}
