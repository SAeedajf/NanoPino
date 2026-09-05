<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

final class CoreContentTypes
{
    public const OWNER = 'cms.core';

    public static function register(ContentTypeRegistry $registry): void
    {
        $registry->register(new ContentTypeDefinition(
            'page',
            self::OWNER,
            'برگه‌ها',
            'برگه',
            fields: ['content', 'featured_media', 'template', 'related_content'],
            taxonomies: [],
            hierarchical: true,
            editor: ['icon' => 'file-text', 'supports_parent' => true],
        ));

        $registry->register(new ContentTypeDefinition(
            'post',
            self::OWNER,
            'نوشته‌ها',
            'نوشته',
            fields: ['content', 'featured_media', 'related_content'],
            taxonomies: ['category', 'tag'],
            hierarchical: false,
            editor: ['icon' => 'notebook-text', 'supports_parent' => false],
        ));
    }
}
