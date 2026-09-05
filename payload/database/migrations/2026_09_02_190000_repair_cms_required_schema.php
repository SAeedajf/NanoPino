<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Pinoox\Component\Migration\MigrationBase;

/**
 * RC repair migration for installations where an earlier pre-1.0 package was
 * extracted/recorded but its physical CMS tables were not created.
 *
 * It deliberately reuses the canonical create migrations instead of copying
 * schema definitions. Every referenced migration is idempotent via hasTable().
 */
return new class extends MigrationBase
{
    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'settings',
        'audit_events',
        'contents',
        'content_fields',
        'content_relations',
        'terms',
        'content_terms',
        'content_revisions',
        'media_assets',
        'media_usages',
        'media_variants',
        'theme_previews',
        'builder_documents',
        'builder_revisions',
        'global_blocks',
        'search_documents',
    ];

    /** @var list<string> */
    private const CANONICAL_MIGRATIONS = [
        '2026_09_01_080000_create_cms_settings_table.php',
        '2026_09_01_080100_create_cms_audit_events_table.php',
        '2026_09_01_090000_create_cms_contents_table.php',
        '2026_09_01_090100_create_cms_content_fields_table.php',
        '2026_09_01_090200_create_cms_content_relations_table.php',
        '2026_09_01_090300_create_cms_terms_table.php',
        '2026_09_01_090400_create_cms_content_terms_table.php',
        '2026_09_01_100000_create_cms_content_revisions_table.php',
        '2026_09_01_100100_create_cms_media_assets_table.php',
        '2026_09_01_100200_create_cms_media_usages_table.php',
        '2026_09_01_100300_create_cms_media_variants_table.php',
        '2026_09_01_110000_create_cms_theme_previews_table.php',
        '2026_09_01_130000_create_cms_builder_tables.php',
        '2026_09_01_140000_create_cms_global_blocks_table.php',
        '2026_09_01_170000_create_cms_search_documents_table.php',
    ];

    public function up(): void
    {
        foreach (self::CANONICAL_MIGRATIONS as $file) {
            $path = __DIR__ . '/' . $file;
            if (!is_file($path)) {
                throw new \RuntimeException('Required CMS migration file is missing: ' . $file);
            }

            $migration = require $path;
            if (!is_object($migration) || !method_exists($migration, 'up')) {
                throw new \RuntimeException('Invalid CMS migration contract: ' . $file);
            }

            $migration->up();
        }

        $missing = [];
        foreach (self::REQUIRED_TABLES as $table) {
            if (!$this->schema->hasTable($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                'CMS schema repair completed but required tables are still missing: ' . implode(', ', $missing),
            );
        }
    }

    public function down(): void
    {
        // No-op by design: this migration repairs/adopts the baseline schema
        // and does not own tables independently from the canonical migrations.
    }
};
