<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\ThemeRuntime;

use App\com_pinoox_cms\Cms\Theme\ThemeView;

final readonly class BuilderThemeRenderResult
{
    /** @param list<string> $cacheTags */
    public function __construct(
        public string $html,
        public string $designCss,
        public string $responsiveCss,
        public string $checksum,
        public ThemeView $theme,
        public array $cacheTags = [],
    ) {}
}
