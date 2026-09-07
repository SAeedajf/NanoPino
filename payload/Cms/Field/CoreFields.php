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
            new FieldDefinition(
                'categories',
                self::OWNER,
                FieldType::Taxonomy,
                'دسته‌بندی‌ها',
                FieldStorageStrategy::Taxonomy,
                multiple: true,
                options: ['taxonomy' => 'category', 'max_items' => 50],
                ui: ['component' => 'taxonomy-picker', 'order' => 50],
            ),
            new FieldDefinition(
                'tags',
                self::OWNER,
                FieldType::Taxonomy,
                'برچسب‌ها',
                FieldStorageStrategy::Taxonomy,
                multiple: true,
                options: ['taxonomy' => 'tag', 'max_items' => 100],
                ui: ['component' => 'taxonomy-picker', 'order' => 60],
            ),
        ] as $definition) {
            $registry->register($definition);
        }
    }
}
