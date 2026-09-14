<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Platform;

use InvalidArgumentException;

/**
 * Canonical identity for the independent NanoShell platform.
 *
 * NanoPino is the product/package brand; NanoShell is the platform contract.
 * Source-format readers and legacy import adapters must not define platform
 * identity or public runtime behavior.
 */
final class NanoShellPlatform
{
    public const ID = 'nanoshell';
    public const NAME = 'NanoShell';
    public const CONTRACT = 'nanoshell-platform-v1';
    public const VERSION = 1;

    /** @return array<string,mixed> */
    public static function profile(): array
    {
        return [
            'id' => self::ID,
            'name' => self::NAME,
            'contract' => self::CONTRACT,
            'version' => self::VERSION,
            'standalone' => true,
            'product' => 'NanoPino',
            'runtime' => [
                'execution' => 'native-only',
                'document' => 'block-document-v1',
                'api' => 'cms-api-v1',
                'identity_source' => 'nanoshell-platform-contract',
            ],
            'source_adapters' => [
                'static-theme-import',
                'static-asset-intake',
            ],
        ];
    }

    /** @param array<string,mixed> $profile */
    public static function assertProfile(array $profile): void
    {
        $expected = self::profile();
        $encoded = strtolower(json_encode($profile, JSON_THROW_ON_ERROR));
        if (str_contains($encoded, 'wordpress')) {
            throw new InvalidArgumentException('NanoShell platform profile cannot contain a legacy source identity.');
        }
        foreach (['id', 'name', 'contract', 'version', 'standalone', 'product', 'runtime', 'source_adapters'] as $key) {
            if (($profile[$key] ?? null) !== $expected[$key]) {
                throw new InvalidArgumentException('NanoShell platform profile is invalid.');
            }
        }
    }
}
