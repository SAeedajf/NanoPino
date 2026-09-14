<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeSchemaReconciler;
use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;
use Pinoox\Portal\Database\DB;

/**
 * Adds database-level protection after the CMS baseline has been adopted.
 *
 * The migration fails closed when orphan rows exist. It never deletes or
 * rewrites data, and it is intentionally separate from the original create
 * migrations so older installations can inventory and recover first.
 */
return new class extends MigrationBase
{
    /** @var list<array{table:string,column:string,parent:string,parent_column:string,on_delete:string,name:string}> */
    private const RELATIONS = [
        ['table' => 'content_fields', 'column' => 'content_id', 'parent' => 'contents', 'parent_column' => 'id', 'on_delete' => 'cascade', 'name' => 'cms_fk_content_fields_content'],
        ['table' => 'content_relations', 'column' => 'source_content_id', 'parent' => 'contents', 'parent_column' => 'id', 'on_delete' => 'cascade', 'name' => 'cms_fk_content_rel_source'],
        ['table' => 'content_relations', 'column' => 'target_content_id', 'parent' => 'contents', 'parent_column' => 'id', 'on_delete' => 'restrict', 'name' => 'cms_fk_content_rel_target'],
        ['table' => 'content_terms', 'column' => 'content_id', 'parent' => 'contents', 'parent_column' => 'id', 'on_delete' => 'cascade', 'name' => 'cms_fk_content_terms_content'],
        ['table' => 'content_terms', 'column' => 'term_id', 'parent' => 'terms', 'parent_column' => 'id', 'on_delete' => 'restrict', 'name' => 'cms_fk_content_terms_term'],
        ['table' => 'content_revisions', 'column' => 'content_id', 'parent' => 'contents', 'parent_column' => 'id', 'on_delete' => 'cascade', 'name' => 'cms_fk_content_revisions_content'],
        ['table' => 'media_usages', 'column' => 'media_id', 'parent' => 'media_assets', 'parent_column' => 'id', 'on_delete' => 'restrict', 'name' => 'cms_fk_media_usages_media'],
        ['table' => 'media_variants', 'column' => 'media_id', 'parent' => 'media_assets', 'parent_column' => 'id', 'on_delete' => 'cascade', 'name' => 'cms_fk_media_variants_media'],
        ['table' => 'builder_revisions', 'column' => 'builder_id', 'parent' => 'builder_documents', 'parent_column' => 'id', 'on_delete' => 'cascade', 'name' => 'cms_fk_builder_revisions_builder'],
    ];

    public function up(): void
    {
        $missingTables = CmsRuntimeSchemaReconciler::missingTables('com_pinoox_cms');
        if ($missingTables !== []) {
            throw new \RuntimeException(
                'Cannot adopt CMS relational constraints while tables are missing: ' . implode(', ', $missingTables),
            );
        }

        $connectionName = DB::connectionNameForPackage('com_pinoox_cms');
        $connection = DB::connection($connectionName);
        $schema = DB::schema($connectionName);
        if (!method_exists($schema, 'getForeignKeys')) {
            throw new \RuntimeException('Database driver does not support CMS foreign-key introspection.');
        }

        $orphans = [];
        foreach (self::RELATIONS as $relation) {
            if ($this->hasOrphan($connection, $relation)) {
                $orphans[] = $this->label($relation);
            }
        }
        if ($orphans !== []) {
            throw new \RuntimeException(
                'Refusing CMS relational constraint migration because orphan rows exist: ' . implode(', ', $orphans),
            );
        }

        $added = [];
        try {
            foreach (self::RELATIONS as $relation) {
                if ($this->hasForeignKey($schema->getForeignKeys($relation['table']), $relation)) {
                    continue;
                }

                // Record the intended addition before DDL so a driver that
                // reports an error after applying DDL still gets a cleanup
                // attempt in the failure path.
                $added[] = $relation;
                $schema->table($relation['table'], function (Blueprint $table) use ($relation): void {
                    $foreign = $table
                        ->foreign($relation['column'], $relation['name'])
                        ->references($relation['parent_column'])
                        ->on($relation['parent']);
                    $foreign->onDelete($relation['on_delete']);
                });
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($added) as $relation) {
                try {
                    $schema->table($relation['table'], function (Blueprint $table) use ($relation): void {
                        $table->dropForeign($relation['name']);
                    });
                } catch (\Throwable) {
                    // Preserve the original migration error; down() remains
                    // available for an operator-led cleanup if DDL rollback
                    // is not supported by the target driver.
                }
            }
            throw $exception;
        }
    }

    public function down(): void
    {
        $connectionName = DB::connectionNameForPackage('com_pinoox_cms');
        $schema = DB::schema($connectionName);
        if (!method_exists($schema, 'getForeignKeys')) {
            return;
        }

        foreach (self::RELATIONS as $relation) {
            if (!$schema->hasTable($relation['table'])) {
                continue;
            }

            $owned = false;
            foreach ($schema->getForeignKeys($relation['table']) as $foreignKey) {
                if ((string)($foreignKey['name'] ?? '') === $relation['name']) {
                    $owned = true;
                    break;
                }
            }
            if (!$owned) {
                continue;
            }

            $schema->table($relation['table'], function (Blueprint $table) use ($relation): void {
                $table->dropForeign($relation['name']);
            });
        }
    }

    /** @param list<array<string,mixed>> $foreignKeys @param array{table:string,column:string,parent:string,parent_column:string,on_delete:string,name:string} $relation */
    private function hasForeignKey(array $foreignKeys, array $relation): bool
    {
        foreach ($foreignKeys as $foreignKey) {
            $columns = array_values(array_map('strtolower', (array)($foreignKey['columns'] ?? [])));
            $parentColumns = array_values(array_map('strtolower', (array)($foreignKey['foreign_columns'] ?? [])));
            $parent = strtolower(trim((string)($foreignKey['foreign_table'] ?? ''), '`" '));
            if (str_contains($parent, '.')) {
                $parent = substr($parent, (int)strrpos($parent, '.') + 1);
            }

            $physicalParent = strtolower(DB::physicalTableName($relation['parent'], 'com_pinoox_cms'));
            if (
                $columns === [strtolower($relation['column'])]
                && $parentColumns === [strtolower($relation['parent_column'])]
                && in_array($parent, [strtolower($relation['parent']), $physicalParent], true)
            ) {
                $actualDelete = $this->normalizeDeleteAction($foreignKey['on_delete'] ?? null);
                $expectedDelete = $this->normalizeDeleteAction($relation['on_delete']);
                if ($actualDelete === $expectedDelete) {
                    return true;
                }

                throw new \RuntimeException(
                    'CMS foreign-key delete action mismatch for ' . $this->label($relation)
                    . ': expected ' . $expectedDelete . ', found ' . ($actualDelete ?? 'unknown') . '.',
                );
            }
        }

        return false;
    }

    private function normalizeDeleteAction(mixed $action): ?string
    {
        $action = trim(strtolower((string) $action));
        if ($action === '') {
            return null;
        }

        return preg_replace('/\\s+/', ' ', str_replace('_', ' ', $action)) ?: null;
    }

    /** @param array{table:string,column:string,parent:string,parent_column:string,on_delete:string,name:string} $relation */
    private function hasOrphan(object $connection, array $relation): bool
    {
        $child = DB::physicalTableName($relation['table'], 'com_pinoox_cms');
        $parent = DB::physicalTableName($relation['parent'], 'com_pinoox_cms');

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

    /** @param array{table:string,column:string,parent:string,parent_column:string,on_delete:string,name:string} $relation */
    private function label(array $relation): string
    {
        return $relation['table'] . '.' . $relation['column'] . ' -> ' . $relation['parent'] . '.' . $relation['parent_column'];
    }
};
