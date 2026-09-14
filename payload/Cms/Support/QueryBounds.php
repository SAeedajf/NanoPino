<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Support;

final class QueryBounds
{
    public const MAX_OFFSET = 100_000;

    public static function offset(int $value): int
    {
        return max(0, min(self::MAX_OFFSET, $value));
    }
}
