<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

final readonly class StyleVariation
{
    /** @param array<string,mixed> $tokens */
    public function __construct(
        public string $id,
        public string $title,
        public array $tokens,
        public string $sourceThemePath,
    ) {}
}
