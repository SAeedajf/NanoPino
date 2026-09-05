<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Core;

use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationDefinition;
use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationRegistry;

final class CoreBlockMigrations
{
    public static function register(BlockMigrationRegistry $registry): void
    {
        $registry->register(new BlockMigrationDefinition(
            'block-migration:core/heading:1-2',
            CoreBlocks::OWNER,
            'core/heading',
            1,
            2,
            static function (array $payload): array {
                $attributes = is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [];

                // v1 used `content`; v2 standardizes on `text`.
                if (!array_key_exists('text', $attributes) && array_key_exists('content', $attributes)) {
                    $attributes['text'] = (string)$attributes['content'];
                }
                unset($attributes['content']);

                $level = (int)($attributes['level'] ?? 2);
                $attributes['level'] = max(1, min(6, $level));

                $payload['attributes'] = $attributes;
                return $payload;
            },
        ));
    }
}
