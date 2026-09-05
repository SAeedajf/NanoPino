<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\FullSite;

use App\com_pinoox_cms\Cms\Builder\BuilderPublishedResolver;
use App\com_pinoox_cms\Cms\Theme\ThemeEngine;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateHierarchyResolver;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateRequest;

final readonly class FullSiteTemplateResolver
{
    public function __construct(
        private BuilderPublishedResolver $published,
        private TemplateHierarchyResolver $hierarchy,
        private ThemeEngine $themes,
        private FullSiteTemplateTargetFactory $targets = new FullSiteTemplateTargetFactory(),
    ) {}

    /**
     * Builder Published revisions override Theme files by the same hierarchy candidate.
     *
     * @param array<string,mixed> $designOverrides
     */
    public function resolve(
        int $siteId,
        string $package,
        string $themeName,
        TemplateRequest $request,
        string $locale = 'fa',
        ?string $context = null,
        ?string $styleVariation = null,
        array $designOverrides = [],
    ): ResolvedSiteTemplate {
        $themeView = $this->themes->resolve(
            $package,
            $themeName,
            $request,
            $context,
            $styleVariation,
            $designOverrides,
        );

        $candidates = $this->hierarchy->candidates($request);
        $themeCandidate = $themeView->template?->logicalName;

        // Specificity is the primary ordering. For each candidate, a published
        // Builder override wins over the Theme file of the same candidate.
        // A generic Builder `index` must not shadow a more-specific Theme `page`.
        foreach ($candidates as $candidate) {
            $target = $request->kind === 'part'
                ? $this->targets->part($siteId, $candidate, $locale)
                : $this->targets->template($siteId, $candidate, $locale);

            $revision = $this->published->resolve($target);
            if ($revision !== null) {
                return new ResolvedSiteTemplate($candidate, 'builder', $themeView, $revision);
            }

            if ($themeCandidate === $candidate) {
                return new ResolvedSiteTemplate($candidate, 'theme', $themeView, null);
            }
        }

        return new ResolvedSiteTemplate(
            $candidates[0] ?? ($request->kind === 'part' ? 'part' : 'index'),
            'missing',
            $themeView,
            null,
        );
    }
}
