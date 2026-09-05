<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Command;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;

final readonly class DuplicateBlockCommand implements BuilderCommandInterface
{
    public function __construct(
        private BlockTreeEditor $tree,
        private BlockNodeIdGeneratorInterface $ids,
        public string $blockId,
    ) {}

    public function name(): string { return 'duplicate'; }

    public function apply(BlockDocument $document): BlockDocument
    {
        $location = $this->tree->locate($document, $this->blockId)
            ?? throw new \RuntimeException('Block not found: ' . $this->blockId);

        $copy = $this->cloneNode($location->node);

        return $this->tree->insert(
            $document,
            $location->parentId,
            $location->index + 1,
            $copy,
            $location->slot,
        );
    }

    private function cloneNode(BlockNode $node): BlockNode
    {
        return new BlockNode(
            $this->ids->next($node->id),
            $node->type,
            $node->version,
            $node->attributes,
            $node->styles,
            $node->responsive,
            array_map(fn (BlockNode $child): BlockNode => $this->cloneNode($child), $node->children),
            $node->slot,
        );
    }
}
