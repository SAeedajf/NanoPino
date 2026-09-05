<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Template;

final class CoreTemplateRules
{
    public const OWNER = 'cms.core';

    public static function register(TemplateRuleRegistry $registry): void
    {
        $rules = [
            ['cms.template.home', 'home', ['home', 'index']],
            ['cms.template.page', 'page', ['page-{slug}', 'page', 'single-page', 'single', 'index']],
            ['cms.template.single', 'single', ['single-{type}-{slug}', 'single-{type}', 'single', 'index']],
            ['cms.template.archive', 'archive', ['archive-{type}', 'archive', 'index']],
            ['cms.template.taxonomy', 'taxonomy', ['taxonomy-{taxonomy}-{term}', 'taxonomy-{taxonomy}', 'taxonomy', 'archive', 'index']],
            ['cms.template.search', 'search', ['search', 'index']],
            ['cms.template.404', '404', ['404', 'index']],
            ['cms.template.part', 'part', ['{part}']],
        ];

        foreach ($rules as [$id, $kind, $patterns]) {
            $registry->register(new TemplateRuleDefinition(
                $id,
                self::OWNER,
                $kind,
                $patterns,
                100,
            ));
        }
    }
}
