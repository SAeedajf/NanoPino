<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Builder\FullSite\FullSiteTemplateTargetFactory;

/**
 * Builds an explicit, non-persistent Builder import catalog.
 * Persistence still belongs to the normal BuilderService approval workflow.
 */
final readonly class WordPressBuilderTemplateBridge
{
    public function __construct(private FullSiteTemplateTargetFactory $targets = new FullSiteTemplateTargetFactory()) {}

    public function prepare(
        WordPressThemeStructureReport $report,
        int $siteId,
        string $locale = 'fa',
    ): WordPressBuilderTemplateCatalog {
        if (!$report->safeToUse()) {
            return new WordPressBuilderTemplateCatalog(issues: [[
                'code' => 'builder.catalog_not_safe',
                'severity' => 'blocker',
                'message' => 'WordPress theme structure is not safe to expose to Builder.',
            ]]);
        }

        $templates = [];
        foreach ($report->templates as $item) {
            if ($item->hasBlockers()) continue;
            $templates[] = new WordPressBuilderTemplateCandidate(
                $this->targets->template($siteId, $item->logicalName, $locale),
                'template',
                $item->logicalName,
                $item->sourcePath,
                $item->document->toArray(),
            );
        }

        $parts = [];
        foreach ($report->parts as $item) {
            if ($item->hasBlockers()) continue;
            $parts[] = new WordPressBuilderTemplateCandidate(
                $this->targets->part($siteId, $item->logicalName, $locale),
                'part',
                $item->logicalName,
                $item->sourcePath,
                $item->document->toArray(),
            );
        }

        return new WordPressBuilderTemplateCatalog($templates, $parts, $report->patterns);
    }
}
