<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Pinoox\Component\Migration\MigrationBase;
use Pinoox\Portal\Database\DB;

/**
 * Repairs the pre-0.23.12 CMS table contract.
 *
 * Pinoox app connections own their physical table prefix. CMS migrations and
 * models must therefore use logical table names (e.g. `contents`) and let the
 * `cms_` connection prefix produce the physical `cms_contents` table.
 *
 * Older RC builds incorrectly passed already-prefixed names such as
 * `cms_contents` to the schema builder. On a `cms_` connection that can create
 * legacy double-prefixed tables (`cms_cms_contents`) while runtime queries
 * correctly target `cms_contents`. This migration repairs that state on the
 * exact runtime package connection and then reuses the canonical migrations to
 * create any still-missing tables.
 */
return new class extends MigrationBase
{
    private const PACKAGE = 'com_pinoox_cms';

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
        $connectionName = DB::connectionNameForPackage(self::PACKAGE);
        $schema = DB::schema($connectionName);

        // Force every reused canonical migration onto the same connection used
        // by CMS runtime repositories. Do not depend on ambient App context.
        $this->schema = $schema;

        foreach (self::REQUIRED_TABLES as $logical) {
            if ($schema->hasTable($logical)) {
                continue;
            }

            // Old RCs supplied the physical-ish `cms_*` name to a connection
            // which already had the `cms_` prefix. Schema grammar therefore
            // produced `cms_cms_*`. Rename in place to preserve any data.
            $legacyLogical = 'cms_' . $logical;
            if ($schema->hasTable($legacyLogical)) {
                $schema->rename($legacyLogical, $logical);
            }
        }

        foreach (self::CANONICAL_MIGRATIONS as $file) {
            $path = __DIR__ . '/' . $file;
            if (!is_file($path)) {
                throw new \RuntimeException('Required CMS migration file is missing: ' . $file);
            }

            $migration = require $path;
            if (!is_object($migration) || !method_exists($migration, 'up')) {
                throw new \RuntimeException('Invalid CMS migration contract: ' . $file);
            }

            if (property_exists($migration, 'schema')) {
                $migration->schema = $schema;
            }

            $migration->up();
        }

        $missing = [];
        foreach (self::REQUIRED_TABLES as $logical) {
            if (!$schema->hasTable($logical)) {
                $missing[] = $logical;
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                'CMS logical table contract repair failed; required tables are still missing: '
                . implode(', ', $missing),
            );
        }
    }

    public function down(): void
    {
        // No-op: this migration repairs/adopts the baseline tables and must not
        // destroy production data during rollback of the application package.
    }
};
