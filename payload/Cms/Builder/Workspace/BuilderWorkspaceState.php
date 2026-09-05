<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Workspace;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;

final class BuilderWorkspaceState
{
    public function __construct(
        private BlockDocument $document,
        private readonly BlockTreeEditor $tree = new BlockTreeEditor(),
        private ?string $selectedBlockId = null,
        private BuilderViewport $viewport = BuilderViewport::Desktop,
        private BuilderPanel $panel = BuilderPanel::Blocks,
    ) {}

    public function document(): BlockDocument { return $this->document; }
    public function selectedBlockId(): ?string { return $this->selectedBlockId; }
    public function viewport(): BuilderViewport { return $this->viewport; }
    public function panel(): BuilderPanel { return $this->panel; }

    public function replaceDocument(BlockDocument $document): void
    {
        $this->document = $document;
        if (
            $this->selectedBlockId !== null
            && $this->tree->locate($document, $this->selectedBlockId) === null
        ) {
            $this->selectedBlockId = null;
        }
    }

    public function select(?string $blockId): void
    {
        if ($blockId !== null && $this->tree->locate($this->document, $blockId) === null) {
            throw new \InvalidArgumentException('Selected block does not exist.');
        }
        $this->selectedBlockId = $blockId;
    }

    public function setViewport(BuilderViewport $viewport): void
    {
        $this->viewport = $viewport;
    }

    public function openPanel(BuilderPanel $panel): void
    {
        $this->panel = $panel;
    }
}
