<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Support;

/**
 * Runtime view of the Pinoox-native application release metadata.
 *
 * app.php is the single source of truth because Pinoox/Pinx consumes it when
 * building and installing the package. Keeping a second set of hard-coded
 * version constants here previously allowed the runtime and the .pinx manifest
 * to drift apart.
 */
final class CmsRelease
{
    private const DEFAULT_MIN_PINCORE = '3.14.0';

    /** @var array<string,mixed>|null */
    private static ?array $metadata = null;

    public static function version(): string
    {
        return (string) self::metadataValue('version-name');
    }

    public static function versionCode(): int
    {
        return (int) self::metadataValue('version-code');
    }

    public static function minKernelCode(): int
    {
        return (int) self::metadataValue('minpin');
    }

    public static function minPincore(): string
    {
        $app = self::metadata();
        $requires = $app['cms']['requires'] ?? null;
        $constraint = is_array($requires) ? ($requires['pincore'] ?? null) : null;

        if (!is_string($constraint) || trim($constraint) === '') {
            return self::DEFAULT_MIN_PINCORE;
        }

        return ltrim(trim($constraint), '>=<~^ ');
    }

    /** @return array<string,mixed> */
    public static function metadata(): array
    {
        if (self::$metadata !== null) {
            return self::$metadata;
        }

        $appFile = dirname(__DIR__, 2) . '/app.php';
        if (!is_file($appFile) || is_link($appFile)) {
            throw new \RuntimeException('CMS release metadata source app.php is unavailable.');
        }

        $metadata = include $appFile;
        if (!is_array($metadata)) {
            throw new \RuntimeException('CMS release metadata source app.php is invalid.');
        }

        foreach (['version-name', 'version-code', 'minpin'] as $required) {
            if (!array_key_exists($required, $metadata)) {
                throw new \RuntimeException('CMS release metadata is missing ' . $required . '.');
            }
        }

        self::$metadata = $metadata;

        return self::$metadata;
    }

    /** @return mixed */
    private static function metadataValue(string $key): mixed
    {
        $metadata = self::metadata();

        return $metadata[$key];
    }
}
