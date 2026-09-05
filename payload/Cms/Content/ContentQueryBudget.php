<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

/**
 * Query-count budget for a single repository search() call.
 *
 * These counts describe repository-owned SELECTs and intentionally exclude
 * authentication/authorization/audit queries performed by higher layers.
 */
final class ContentQueryBudget
{
    public const LIST_TARGET = 1;
    public const LIST_LIMIT = 1;
    public const FULL_TARGET = 4;
    public const FULL_LIMIT = 4;

    public static function limit(ContentProjection $projection): int
    {
        return $projection === ContentProjection::List
            ? self::LIST_LIMIT
            : self::FULL_LIMIT;
    }
}
