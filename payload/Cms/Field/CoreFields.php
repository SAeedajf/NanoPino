<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Field;

final class CoreFields
{
    public const OWNER = 'cms.core';

    public static function register(FieldRegistry $registry): void
    {
        foreach ([
            new FieldDefinition(
                'content',
                self::OWNER,
                FieldType::RichText,
                'محتوا',
                FieldStorageStrategy::Document,
                options: ['max_length' => 1000000, 'trust' => 'untrusted'],
                ui: ['component' => 'richtext', 'order' => 10],
            ),
            new FieldDefinition(
                'featured_media',
                self::OWNER,
                FieldType::Media,
                'تصویر شاخص',
                FieldStorageStrategy::Meta,
                ui: ['component' => 'media', 'order' => 20],
            ),
            new FieldDefinition(
                'template',
                self::OWNER,
                FieldType::Text,
                'قالب',
                FieldStorageStrategy::Meta,
                default: 'default',
                ui: ['component' => 'template-select', 'order' => 30],
            ),
            new FieldDefinition(
                'related_content',
                self::OWNER,
                FieldType::Relation,
                'محتوای مرتبط',
                FieldStorageStrategy::Relation,
                multiple: true,
                options: ['max_items' => 50],
                ui: ['component' => 'content-relation', 'order' => 40],
            ),
        ] as $definition) {
            $registry->register($definition);
        }
    }
}
