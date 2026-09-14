<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Core;

use App\com_pinoox_cms\Cms\Block\Document\BlockNode;
use App\com_pinoox_cms\Cms\Block\Render\BlockRenderContext;
use App\com_pinoox_cms\Cms\Block\Render\BlockRendererInterface;
use App\com_pinoox_cms\Cms\Block\Render\BlockRendererRegistry;
use App\com_pinoox_cms\Cms\Block\Render\HtmlEscaper;
use App\com_pinoox_cms\Cms\Block\Render\RenderedBlock;

final class CoreBlockRenderers implements BlockRendererInterface
{
    public function __construct(private readonly HtmlEscaper $html = new HtmlEscaper()) {}

    public static function register(BlockRendererRegistry $registry): void
    {
        $renderer = new self();
        foreach (['core/section', 'core/heading', 'core/paragraph', 'core/button'] as $type) {
            $registry->register($type, CoreBlocks::OWNER, $renderer);
        }
    }

    public function supports(string $blockType): bool
    {
        return in_array($blockType, ['core/section', 'core/heading', 'core/paragraph', 'core/button'], true);
    }

    public function render(
        BlockNode $node,
        BlockRenderContext $context,
        array $childrenHtml,
    ): RenderedBlock {
        return match ($node->type) {
            'core/section' => $this->section($node, $childrenHtml),
            'core/heading' => $this->heading($node),
            'core/paragraph' => $this->paragraph($node),
            'core/button' => $this->button($node),
            default => throw new \RuntimeException('Unsupported core block renderer type.'),
        };
    }

    /** @param list<string> $children */
    private function section(BlockNode $node, array $children): RenderedBlock
    {
        $tag = (string)($node->attributes['tag'] ?? 'section');
        if (!in_array($tag, ['section', 'div', 'main', 'article', 'aside', 'header', 'footer'], true)) {
            $tag = 'section';
        }

        $class = $this->html->classList('cms-block-section', (string)($node->attributes['className'] ?? ''));
        return new RenderedBlock(
            '<' . $tag . ' class="' . $this->html->text($class) . '"' . $this->nodePresentation($node) . '>' .
            implode('', $children) .
            '</' . $tag . '>'
        );
    }

    private function heading(BlockNode $node): RenderedBlock
    {
        $level = max(1, min(6, (int)($node->attributes['level'] ?? 2)));
        $text = $this->html->text($node->attributes['text'] ?? '');

        return new RenderedBlock('<h' . $level . $this->nodePresentation($node) . '>' . $text . '</h' . $level . '>');
    }

    private function paragraph(BlockNode $node): RenderedBlock
    {
        return new RenderedBlock(
            '<p' . $this->nodePresentation($node) . '>' . $this->html->text($node->attributes['text'] ?? '') . '</p>'
        );
    }

    private function button(BlockNode $node): RenderedBlock
    {
        $label = $this->html->text($node->attributes['label'] ?? '');
        $url = $this->html->url($node->attributes['url'] ?? '#');
        $newTab = (bool)($node->attributes['newTab'] ?? false);

        $target = $newTab ? ' target="_blank" rel="noopener noreferrer"' : '';

        return new RenderedBlock(
            '<a class="cms-block-button" href="' . $url . '"' . $target . $this->nodePresentation($node) . '>' .
            $label .
            '</a>'
        );
    }

    private function nodePresentation(BlockNode $node): string
    {
        $styleMap = [
            'color' => 'color',
            'backgroundColor' => 'background-color',
            'fontSize' => 'font-size',
            'fontWeight' => 'font-weight',
            'lineHeight' => 'line-height',
            'letterSpacing' => 'letter-spacing',
            'margin' => 'margin',
            'padding' => 'padding',
            'width' => 'width',
            'height' => 'height',
            'minWidth' => 'min-width',
            'maxWidth' => 'max-width',
            'minHeight' => 'min-height',
            'maxHeight' => 'max-height',
            'display' => 'display',
            'alignItems' => 'align-items',
            'justifyContent' => 'justify-content',
            'gap' => 'gap',
            'gridTemplateColumns' => 'grid-template-columns',
            'textAlign' => 'text-align',
            'borderRadius' => 'border-radius',
            'borderWidth' => 'border-width',
            'borderColor' => 'border-color',
            'opacity' => 'opacity',
        ];
        $declarations = [];
        foreach ($node->styles as $key => $value) {
            if ($value === null || !isset($styleMap[$key]) || trim((string)$value) === '') continue;
            $declarations[] = $styleMap[$key] . ':' . trim((string)$value) . ';';
        }

        $attributes = ' data-cms-block-id="' . $this->html->text($node->id) . '"';
        if ($declarations !== []) {
            $attributes .= ' style="' . $this->html->text(implode('', $declarations)) . '"';
        }
        return $attributes;
    }
}
