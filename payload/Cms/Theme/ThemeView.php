<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use App\com_pinoox_cms\Cms\Theme\Design\ResolvedDesign;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePattern;
use App\com_pinoox_cms\Cms\Theme\Template\ResolvedTemplate;

final readonly class ThemeView
{
    /**
     * @param array<string,ThemePattern> $patterns
     */
    public function __construct(
        public ThemeDefinition $theme,
        public NativeThemeStack $stack,
        public ?ResolvedTemplate $template,
        public ResolvedDesign $design,
        public array $patterns,
    ) {}
}
