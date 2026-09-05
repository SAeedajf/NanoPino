<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use Pinoox\Portal\Lang;

final class AdminI18n
{
    public const GROUP = 'admin';
    public const FALLBACK_LOCALE = 'en';

    public static function text(string $key, array $replace = [], ?string $locale = null): string
    {
        $value = Lang::get(self::GROUP . '.' . ltrim($key, '.'), $replace, $locale, true);

        return is_string($value) ? $value : (string) ($key ?: '');
    }

    /** @return array<string,mixed> */
    public static function catalog(?string $locale = null): array
    {
        $value = Lang::get(self::GROUP, [], $locale, true);
        return is_array($value) ? $value : [];
    }

    public static function direction(string $locale): string
    {
        $language = strtolower(strtok(str_replace('_', '-', $locale), '-') ?: $locale);
        return in_array($language, ['fa', 'ar', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    }
}
