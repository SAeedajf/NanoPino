<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Pattern;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Builder\Command\BlockNodeIdGeneratorInterface;
use App\com_pinoox_cms\Cms\Builder\Tree\BlockTreeEditor;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePattern;

final readonly class PatternInsertionService
{
    public function __construct(
        private BlockDocumentLoader $loader,
        private BlockTreeEditor $tree,
        private BlockNodeIdGeneratorInterface $ids,
    ) {}

    public function command(
        ThemePattern $pattern,
        ?string $parentId,
        int $index,
        ?string $slot = null,
    ): InsertPatternCommand {
        $document = $this->loader->fromArray($pattern->document);

        return new InsertPatternCommand(
            $this->tree,
            $this->ids,
            $document,
            $parentId,
            $index,
            $slot,
        );
    }
}
