<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

final class CoreTaxonomies
{
    public const OWNER = 'cms.core';

    public static function register(TaxonomyRegistry $registry): void
    {
        $registry->register(new TaxonomyDefinition(
            'category',
            self::OWNER,
            'دسته‌بندی‌ها',
            'دسته‌بندی',
            true,
            ['post'],
        ));

        $registry->register(new TaxonomyDefinition(
            'tag',
            self::OWNER,
            'برچسب‌ها',
            'برچسب',
            false,
            ['post'],
        ));
    }
}
