<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Runtime;

use Pinoox\Portal\Database\DB;

/**
 * Read-only relational integrity probe for CMS-owned tables.
 *
 * This deliberately reports missing constraints instead of adding them during
 * boot or health execution. Constraint adoption is a migration concern and
 * must first pass an orphan-data scan on the target database.
 */
final class CmsSchemaIntegrityProbe
{
    /** @var list<array{table:string,column:string,parent:string,parent_column:string}> */
    private const RELATIONS = [
        ['table' => 'content_fields', 'column' => 'content_id', 'parent' => 'contents', 'parent_column' => 'id'],
        ['table' => 'content_relations', 'column' => 'source_content_id', 'parent' => 'contents', 'parent_column' => 'id'],
        ['table' => 'content_relations', 'column' => 'target_content_id', 'parent' => 'contents', 'parent_column' => 'id'],
        ['table' => 'content_terms', 'column' => 'content_id', 'parent' => 'contents', 'parent_column' => 'id'],
        ['table' => 'content_terms', 'column' => 'term_id', 'parent' => 'terms', 'parent_column' => 'id'],
        ['table' => 'content_revisions', 'column' => 'content_id', 'parent' => 'contents', 'parent_column' => 'id'],
        ['table' => 'media_usages', 'column' => 'media_id', 'parent' => 'media_assets', 'parent_column' => 'id'],
        ['table' => 'media_variants', 'column' => 'media_id', 'parent' => 'media_assets', 'parent_column' => 'id'],
        ['table' => 'builder_revisions', 'column' => 'builder_id', 'parent' => 'builder_documents', 'parent_column' => 'id'],
    ];

    /**
     * @return list<array{table:string,column:string,parent:string,parent_column:string}>
     */
    public static function expectedRelations(): array
    {
        return self::RELATIONS;
    }

    /**
     * @return array<string,mixed>
     */
    public static function inspect(string $package = 'com_pinoox_cms'): array
    {
        $missingTables = CmsRuntimeSchemaReconciler::missingTables($package);
        if ($missingTables !== []) {
            return [
                'status' => 'error',
                'message' => 'CMS relational integrity could not be checked because the schema is incomplete.',
                'details' => [
                    'read_only_probe' => true,
                    'missing_tables' => $missingTables,
                    'expected_relations' => count(self::RELATIONS),
                    'foreign_key_introspection_supported' => false,
                ],
            ];
        }

        $connectionName = DB::connectionNameForPackage($package);
        $connection = DB::connection($connectionName);
        $schema = DB::schema($connectionName);

        if (!method_exists($schema, 'getForeignKeys')) {
            return [
                'status' => 'unknown',
                'message' => 'Database driver does not expose foreign-key introspection.',
                'details' => [
                    'read_only_probe' => true,
                    'expected_relations' => count(self::RELATIONS),
                    'foreign_key_introspection_supported' => false,
                ],
            ];
        }

        $missingForeignKeys = [];
        $orphanRelations = [];

        foreach (self::RELATIONS as $relation) {
            $foreignKeys = $schema->getForeignKeys($relation['table']);
            if (!self::hasForeignKey($foreignKeys, $relation, $package)) {
                $missingForeignKeys[] = self::relationLabel($relation);
            }

            if (self::hasOrphan($connection, $relation, $package)) {
                $orphanRelations[] = self::relationLabel($relation);
            }
        }

        $status = $orphanRelations !== []
            ? 'error'
            : ($missingForeignKeys !== [] ? 'warning' : 'ok');

        return [
            'status' => $status,
            'message' => match ($status) {
                'ok' => 'CMS relational integrity constraints and orphan scan passed.',
                'warning' => 'CMS schema has no orphan relation detected, but one or more foreign keys are missing.',
                default => 'CMS schema contains orphan relations and requires repair before constraint adoption.',
            },
            'details' => [
                'read_only_probe' => true,
                'expected_relations' => count(self::RELATIONS),
                'missing_foreign_keys' => $missingForeignKeys,
                'orphan_relations' => $orphanRelations,
                'foreign_key_introspection_supported' => true,
            ],
        ];
    }

    /** @param list<array<string,mixed>> $foreignKeys @param array{table:string,column:string,parent:string,parent_column:string} $relation */
    private static function hasForeignKey(array $foreignKeys, array $relation, string $package): bool
    {
        foreach ($foreignKeys as $foreignKey) {
            $columns = array_values(array_map('strtolower', (array)($foreignKey['columns'] ?? [])));
            $parentColumns = array_values(array_map('strtolower', (array)($foreignKey['foreign_columns'] ?? [])));
            $foreignTable = strtolower(trim((string)($foreignKey['foreign_table'] ?? ''), '`" '));
            if (str_contains($foreignTable, '.')) {
                $foreignTable = substr($foreignTable, (int)strrpos($foreignTable, '.') + 1);
            }

            $physicalParent = strtolower(DB::physicalTableName($relation['parent'], $package));
            if (
                $columns === [strtolower($relation['column'])]
                && $parentColumns === [strtolower($relation['parent_column'])]
                && in_array($foreignTable, [strtolower($relation['parent']), $physicalParent], true)
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param array{table:string,column:string,parent:string,parent_column:string} $relation */
    private static function hasOrphan(object $connection, array $relation, string $package): bool
    {
        $child = DB::physicalTableName($relation['table'], $package);
        $parent = DB::physicalTableName($relation['parent'], $package);

        return $connection
            ->table($child . ' as child')
            ->leftJoin(
                $parent . ' as parent',
                'child.' . $relation['column'],
                '=',
                'parent.' . $relation['parent_column'],
            )
            ->whereNotNull('child.' . $relation['column'])
            ->whereNull('parent.' . $relation['parent_column'])
            ->exists();
    }

    /** @param array{table:string,column:string,parent:string,parent_column:string} $relation */
    private static function relationLabel(array $relation): string
    {
        return $relation['table'] . '.' . $relation['column'] . ' -> ' . $relation['parent'] . '.' . $relation['parent_column'];
    }
}
