<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Render;

use App\com_pinoox_cms\Cms\Block\Document\BlockNode;

interface BlockRendererInterface
{
    public function supports(string $blockType): bool;

    /**
     * Child HTML has already been rendered through the same registry.
     *
     * @param list<string> $childrenHtml
     */
    public function render(
        BlockNode $node,
        BlockRenderContext $context,
        array $childrenHtml,
    ): RenderedBlock;
}
