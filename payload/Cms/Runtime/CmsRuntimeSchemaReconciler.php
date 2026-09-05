<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Runtime;

use Pinoox\Portal\Database\DB;

/**
 * Read-only runtime schema health probe.
 *
 * Migration is intentionally NOT performed here. Pinoox PINX installer/update
 * owns migration execution. Running Migrator from app boot can happen before
 * the installer reaches its migrate stage and can leave an extracted update
 * in a failed/blank state.
 */
final class CmsRuntimeSchemaReconciler
{
    /** @var list<string> */
    public const REQUIRED_TABLES = [
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

    /**
     * Backward-compatible read-only probe. Returns missing tables; it does not
     * mutate schema and does not throw just because installation is in-flight.
     *
     * @return list<string>
     */
    public static function ensure(string $package = 'com_pinoox_cms'): array
    {
        return self::missingTables($package);
    }

    /** @return list<string> */
    public static function missingTables(string $package = 'com_pinoox_cms'): array
    {
        $connectionName = DB::connectionNameForPackage($package);
        $connection = DB::connection($connectionName);
        $driver = strtolower((string) $connection->getDriverName());

        if ($driver !== 'sqlite') {
            $database = (string) $connection->getDatabaseName();
            if ($database !== '') {
                $physicalToLogical = [];
                foreach (self::REQUIRED_TABLES as $logical) {
                    $physicalToLogical[DB::physicalTableName($logical, $package)] = $logical;
                }

                $physicalNames = array_keys($physicalToLogical);
                if ($physicalNames !== []) {
                    $placeholders = implode(',', array_fill(0, count($physicalNames), '?'));
                    $rows = $connection->select(
                        'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name IN (' . $placeholders . ')',
                        array_merge([$database], $physicalNames),
                    );

                    $present = [];
                    foreach ($rows as $row) {
                        $name = is_object($row) ? ($row->table_name ?? $row->TABLE_NAME ?? null) : null;
                        if (is_string($name) && $name !== '') {
                            $present[$name] = true;
                        }
                    }

                    $missing = [];
                    foreach ($physicalToLogical as $physical => $logical) {
                        if (!isset($present[$physical])) {
                            $missing[] = $logical;
                        }
                    }

                    return $missing;
                }
            }
        }

        $schema = DB::schema($connectionName);
        $missing = [];
        foreach (self::REQUIRED_TABLES as $logical) {
            $physical = DB::physicalTableName($logical, $package);
            $prefix = (string) $connection->getTablePrefix();
            $schemaName = $physical;
            if ($prefix !== '' && str_starts_with($physical, $prefix)) {
                $schemaName = substr($physical, strlen($prefix));
            }
            if (!$schema->hasTable($schemaName)) {
                $missing[] = $logical;
            }
        }

        return $missing;
    }

    public static function assertReady(string $package = 'com_pinoox_cms'): void
    {
        $missing = self::missingTables($package);
        if ($missing !== []) {
            throw new \RuntimeException(
                'CMS schema is incomplete. Run the package migrations: ' . implode(', ', $missing),
            );
        }
    }
}
