<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\FullSite;

use App\com_pinoox_cms\Cms\Builder\Revision\BuilderRevisionRecord;
use App\com_pinoox_cms\Cms\Theme\ThemeView;

final readonly class ResolvedSiteTemplate
{
    public function __construct(
        public string $candidate,
        public string $source,
        public ThemeView $theme,
        public ?BuilderRevisionRecord $builderRevision = null,
    ) {}

    public function isBuilderOverride(): bool
    {
        return $this->source === 'builder';
    }
}
