<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\PublicSite;

final class PublicSlugPolicy
{
    private const PATTERN = '/^[\p{L}\p{N}][\p{L}\p{N}-]{0,159}$/u';

    public static function accepts(string $slug): bool
    {
        return preg_match(self::PATTERN, $slug) === 1;
    }
}
