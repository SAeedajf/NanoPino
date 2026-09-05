<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\DragDrop;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Builder\Command\MoveBlockCommand;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeException;

final readonly class BuilderDragDropService
{
    public function __construct(private BlockTreeEditor $tree) {}

    public function command(BlockDocument $document, BuilderDropIntent $intent): MoveBlockCommand
    {
        if ($this->tree->locate($document, $intent->sourceId) === null) {
            throw new BlockTreeException('Drag source block does not exist.');
        }

        if (
            $intent->targetParentId !== null
            && $this->tree->locate($document, $intent->targetParentId) === null
        ) {
            throw new BlockTreeException('Drop target parent does not exist.');
        }

        if ($intent->targetParentId === $intent->sourceId) {
            throw new BlockTreeException('Block cannot be dropped into itself.');
        }

        if (
            $intent->targetParentId !== null
            && $this->tree->contains($document, $intent->sourceId, $intent->targetParentId)
        ) {
            throw new BlockTreeException('Block cannot be dropped into its descendant.');
        }

        return new MoveBlockCommand(
            $this->tree,
            $intent->sourceId,
            $intent->targetParentId,
            $intent->index,
            $intent->slot,
        );
    }
}
