<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Command;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;

final readonly class InsertBlockCommand implements BuilderCommandInterface
{
    public function __construct(
        private BlockTreeEditor $tree,
        public BlockNode $node,
        public ?string $parentId,
        public int $index,
        public ?string $slot = null,
    ) {}

    public function name(): string { return 'insert'; }

    public function apply(BlockDocument $document): BlockDocument
    {
        return $this->tree->insert($document, $this->parentId, $this->index, $this->node, $this->slot);
    }
}
