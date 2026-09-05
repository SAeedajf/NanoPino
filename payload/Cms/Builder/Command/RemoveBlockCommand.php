<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Command;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;

final readonly class RemoveBlockCommand implements BuilderCommandInterface
{
    public function __construct(
        private BlockTreeEditor $tree,
        public string $blockId,
    ) {}

    public function name(): string { return 'remove'; }

    public function apply(BlockDocument $document): BlockDocument
    {
        return $this->tree->remove($document, $this->blockId)->document;
    }
}
