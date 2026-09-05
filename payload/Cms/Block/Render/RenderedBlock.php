<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Render;

final readonly class RenderedBlock
{
    /** @param list<string> $cacheTags */
    public function __construct(
        public string $html,
        public array $cacheTags = [],
    ) {}
}
