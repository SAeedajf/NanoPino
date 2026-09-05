<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Render;

final readonly class BlockRenderContext
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public int $siteId = 1,
        public ?int $contentId = null,
        public string $locale = 'fa',
        public array $data = [],
    ) {}
}
