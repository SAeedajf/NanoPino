<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Command;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;

final readonly class UpdateBlockCommand implements BuilderCommandInterface
{
    /**
     * null means preserve the current section.
     *
     * @param array<string,mixed>|null $attributes
     * @param array<string,mixed>|null $styles
     * @param array<string,mixed>|null $responsive
     */
    public function __construct(
        private BlockTreeEditor $tree,
        public string $blockId,
        public ?array $attributes = null,
        public ?array $styles = null,
        public ?array $responsive = null,
    ) {}

    public function name(): string { return 'update'; }

    public function apply(BlockDocument $document): BlockDocument
    {
        $location = $this->tree->locate($document, $this->blockId)
            ?? throw new \RuntimeException('Block not found: ' . $this->blockId);

        $current = $location->node;

        $replacement = new BlockNode(
            $current->id,
            $current->type,
            $current->version,
            $this->attributes ?? $current->attributes,
            $this->styles ?? $current->styles,
            $this->responsive ?? $current->responsive,
            $current->children,
            $current->slot,
        );

        return $this->tree->replace($document, $this->blockId, $replacement);
    }
}
