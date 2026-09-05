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
            '<' . $tag . ' class="' . $this->html->text($class) . '">' .
            implode('', $children) .
            '</' . $tag . '>'
        );
    }

    private function heading(BlockNode $node): RenderedBlock
    {
        $level = max(1, min(6, (int)($node->attributes['level'] ?? 2)));
        $text = $this->html->text($node->attributes['text'] ?? '');

        return new RenderedBlock('<h' . $level . '>' . $text . '</h' . $level . '>');
    }

    private function paragraph(BlockNode $node): RenderedBlock
    {
        return new RenderedBlock(
            '<p>' . $this->html->text($node->attributes['text'] ?? '') . '</p>'
        );
    }

    private function button(BlockNode $node): RenderedBlock
    {
        $label = $this->html->text($node->attributes['label'] ?? '');
        $url = $this->html->url($node->attributes['url'] ?? '#');
        $newTab = (bool)($node->attributes['newTab'] ?? false);

        $target = $newTab ? ' target="_blank" rel="noopener noreferrer"' : '';

        return new RenderedBlock(
            '<a class="cms-block-button" href="' . $url . '"' . $target . '>' .
            $label .
            '</a>'
        );
    }
}
