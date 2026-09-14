<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Support;

/**
 * Normalizes user supplied text before it is used as a SQL LIKE pattern.
 *
 * This keeps wildcard expansion predictable and puts a hard upper bound on
 * the amount of text the database has to scan for list/search endpoints.
 */
final class SearchTerm
{
    private const MAX_LENGTH = 120;

    public static function contains(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = function_exists('mb_substr')
            ? mb_substr($value, 0, self::MAX_LENGTH, 'UTF-8')
            : substr($value, 0, self::MAX_LENGTH);

        return '%' . addcslashes($value, '%_\\') . '%';
    }
}
