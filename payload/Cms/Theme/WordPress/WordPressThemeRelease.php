<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

/**
 * Stable identity and release numbering for converted WordPress themes.
 *
 * WordPress themes expose a human version in style.css, while Pinoox needs a
 * monotonic integer version-code for update ordering. Keeping this mapping in
 * one place prevents preview, packaging and update guards from disagreeing.
 */
final class WordPressThemeRelease
{
    public static function normalizeVersion(mixed $value): string
    {
        $version = trim((string)$value);
        return preg_match('/^[0-9]+(?:\.[0-9]+){0,3}(?:[-+][a-z0-9.-]+)?$/i', $version) === 1
            ? $version
            : '0.1.0';
    }

    /**
     * Encodes major/minor/patch into a bounded monotonic Pinoox version-code.
     * The fourth numeric segment is intentionally folded into patch precision.
     */
    public static function versionCode(mixed $value): int
    {
        $version = self::normalizeVersion($value);
        preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:\.(\d+))?/i', $version, $match);
        $major = min((int)($match[1] ?? 0), 2000);
        $minor = min((int)($match[2] ?? 0), 999);
        $patch = min((int)($match[3] ?? 0), 998);
        $build = min((int)($match[4] ?? 0), 1);

        return max(1, ($major * 1_000_000) + ($minor * 1_000) + ($patch * 2) + $build);
    }

    public static function themeName(?string $themeRoot, mixed $textDomain, ?string $sourceSha256 = null): string
    {
        $raw = basename((string)($themeRoot ?: $textDomain ?: 'imported-theme'));
        $raw = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $raw) ?? 'imported-theme');
        $raw = trim($raw, '-_');
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $raw) === 1) return $raw;
        return 'wp-theme-' . substr((string)($sourceSha256 ?: hash('sha256', $raw)), 0, 12);
    }

    /** @return -1|0|1 */
    public static function compare(mixed $left, mixed $right): int
    {
        return self::versionCode($left) <=> self::versionCode($right);
    }
}
