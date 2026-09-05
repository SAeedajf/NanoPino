<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Pattern;

final readonly class ThemePattern
{
    /**
     * @param list<string> $categories
     * @param array<string,mixed> $document
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $categories,
        public array $document,
        public string $sourceThemePath,
    ) {}
}
