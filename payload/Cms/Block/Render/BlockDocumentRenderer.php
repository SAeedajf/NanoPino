<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Render;

use App\com_pinoox_cms\Cms\Block\BlockRegistry;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use RuntimeException;
use App\com_pinoox_cms\Cms\Performance\PerformanceMetric;
use App\com_pinoox_cms\Cms\Performance\PerformanceProfiler;

final class BlockDocumentRenderer
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly BlockDocumentValidator $validator,
        private readonly BlockRendererRegistry $renderers,
        private readonly ?PerformanceProfiler $performance = null,
    ) {}

    public function render(BlockDocument $document, BlockRenderContext $context): RenderedBlock
    {
        if ($this->performance === null) {
            return $this->renderMeasured($document,$context);
        }

        return $this->performance->measure(
            'builder.block_document.render',
            PerformanceMetric::BuilderRenderMs,
            fn(): RenderedBlock => $this->renderMeasured($document,$context),
            ['blocks'=>count($document->blocks)],
        );
    }

    private function renderMeasured(BlockDocument $document, BlockRenderContext $context): RenderedBlock
    {
        $this->validator->validate($document);

        $html = [];
        $tags = [];

        foreach ($document->blocks as $node) {
            $rendered = $this->renderNode($node, $context);
            $html[] = $rendered->html;
            $tags = array_merge($tags, $rendered->cacheTags);
        }

        return new RenderedBlock(
            implode('', $html),
            array_values(array_unique($tags)),
        );
    }

    private function renderNode(BlockNode $node, BlockRenderContext $context): RenderedBlock
    {
        $definition = $this->blocks->definition($node->type)
            ?? throw new RuntimeException('Unknown block definition during render: ' . $node->type);

        $renderer = $this->renderers->renderer($node->type)
            ?? throw new RuntimeException('No renderer registered for block: ' . $node->type);

        if ($this->renderers->owner($node->type) !== $definition->owner()) {
            throw new RuntimeException(
                'Renderer owner mismatch for block: ' . $node->type
            );
        }

        $childHtml = [];
        $tags = [];

        foreach ($node->children as $child) {
            $rendered = $this->renderNode($child, $context);
            $childHtml[] = $rendered->html;
            $tags = array_merge($tags, $rendered->cacheTags);
        }

        $rendered = $renderer->render($node, $context, $childHtml);

        return new RenderedBlock(
            $rendered->html,
            array_values(array_unique(array_merge($tags, $rendered->cacheTags))),
        );
    }
}
