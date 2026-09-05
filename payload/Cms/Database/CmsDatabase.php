<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Pinoox\Portal\Database\DB;

/**
 * Canonical CMS database boundary.
 *
 * Never relies on App::package() / the ambient host application. Every CMS
 * query is pinned to the package connection that Pinoox migrations use for
 * com_pinoox_cms. This keeps runtime repositories and installer migrations on
 * the exact same connection/table naming contract.
 */
final class CmsDatabase
{
    public const PACKAGE = 'com_pinoox_cms';

    public static function connectionName(): string
    {
        return DB::connectionNameForPackage(self::PACKAGE);
    }

    public static function connection(): Connection
    {
        return DB::connection(self::connectionName());
    }

    public static function tableName(string $logical): string
    {
        return DB::tableName($logical, self::PACKAGE);
    }

    public static function physicalTableName(string $logical): string
    {
        return DB::physicalTableName($logical, self::PACKAGE);
    }

    public static function table(string $logical): Builder
    {
        // Passing an explicit connection prevents DB::currentTable() from
        // re-resolving the table against the ambient App::package().
        return DB::table(self::tableName($logical), null, self::connectionName());
    }

    public static function transaction(callable $callback): mixed
    {
        return self::connection()->transaction($callback);
    }

    public static function hasTable(string $logical): bool
    {
        $connection = self::connection();
        $schema = DB::schema(self::connectionName());
        $physical = self::physicalTableName($logical);
        $prefix = (string) $connection->getTablePrefix();
        $schemaName = $physical;

        if ($prefix !== '' && str_starts_with($physical, $prefix)) {
            $schemaName = substr($physical, strlen($prefix));
        }

        return $schema->hasTable($schemaName);
    }

    /** @return array{package:string,connection:string,logical:string,physical:string,exists:bool} */
    public static function diagnostic(string $logical): array
    {
        return [
            'package' => self::PACKAGE,
            'connection' => self::connectionName(),
            'logical' => $logical,
            'physical' => self::physicalTableName($logical),
            'exists' => self::hasTable($logical),
        ];
    }
}
