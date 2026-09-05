<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Pattern;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use App\com_pinoox_cms\Cms\Builder\Command\BlockNodeIdGeneratorInterface;
use App\com_pinoox_cms\Cms\Builder\Command\BuilderCommandInterface;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;

final readonly class InsertPatternCommand implements BuilderCommandInterface
{
    public function __construct(
        private BlockTreeEditor $tree,
        private BlockNodeIdGeneratorInterface $ids,
        private BlockDocument $pattern,
        public ?string $parentId,
        public int $index,
        public ?string $slot = null,
    ) {}

    public function name(): string
    {
        return 'insert_pattern';
    }

    public function apply(BlockDocument $document): BlockDocument
    {
        $current = $document;
        $offset = 0;

        foreach ($this->pattern->blocks as $block) {
            $current = $this->tree->insert(
                $current,
                $this->parentId,
                $this->index + $offset,
                $this->cloneNode($block, $this->slot),
                $this->slot,
            );
            ++$offset;
        }

        return $current;
    }

    private function cloneNode(BlockNode $node, ?string $rootSlot = null): BlockNode
    {
        return new BlockNode(
            $this->ids->next($node->id),
            $node->type,
            $node->version,
            $node->attributes,
            $node->styles,
            $node->responsive,
            array_map(
                fn (BlockNode $child): BlockNode => $this->cloneNode($child),
                $node->children,
            ),
            $rootSlot ?? $node->slot,
        );
    }
}
