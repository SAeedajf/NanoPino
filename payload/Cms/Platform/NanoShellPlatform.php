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
    public const MIN_COMPATIBLE_VERSION = 1;
    public const HOST_BINDINGS = 'pinoox-native';

    /** @var list<string> */
    private const CAPABILITIES = [
        'auth.rbac',
        'content.editorial',
        'storage.native',
        'theme.native',
        'builder.native',
        'extensions.signed',
        'recovery.safe-mode',
    ];

    /** @var ?array<string,mixed> */
    private static ?array $cachedProfile = null;

    /** @return array<string,mixed> */
    public static function profile(): array
    {
        return self::$cachedProfile ??= [
            'id' => self::ID,
            'name' => self::NAME,
            'contract' => self::CONTRACT,
            'version' => self::VERSION,
            'standalone' => true,
            'product' => 'NanoPino',
            'capabilities' => self::CAPABILITIES,
            'compatibility' => [
                'contract' => self::CONTRACT,
                'minimum_version' => self::MIN_COMPATIBLE_VERSION,
                'host_bindings' => self::HOST_BINDINGS,
            ],
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

    public static function supports(string $capability): bool
    {
        return in_array(trim($capability), self::CAPABILITIES, true);
    }

    /** @param array<string,mixed> $profile */
    public static function assertCompatible(array $profile): void
    {
        $version = $profile['version'] ?? null;
        $compatibility = $profile['compatibility'] ?? null;
        $minimumVersion = is_array($compatibility) ? ($compatibility['minimum_version'] ?? null) : null;
        if (
            ($profile['id'] ?? null) !== self::ID
            || ($profile['contract'] ?? null) !== self::CONTRACT
            || !is_int($version)
            || (int) $version < self::MIN_COMPATIBLE_VERSION
            || !is_array($compatibility)
            || !is_int($minimumVersion)
            || (int) $minimumVersion < self::MIN_COMPATIBLE_VERSION
            || (int) $minimumVersion > self::VERSION
            || ($compatibility['contract'] ?? null) !== self::CONTRACT
            || ($compatibility['host_bindings'] ?? null) !== self::HOST_BINDINGS
        ) {
            throw new InvalidArgumentException('NanoShell platform profile is incompatible.');
        }
    }

    /** @param array<string,mixed> $profile */
    public static function assertProfile(array $profile): void
    {
        $expected = self::profile();
        $encoded = strtolower(json_encode($profile, JSON_THROW_ON_ERROR));
        if (str_contains($encoded, 'wordpress')) {
            throw new InvalidArgumentException('NanoShell platform profile cannot contain a legacy source identity.');
        }
        self::assertCompatible($profile);
        foreach (['id', 'name', 'contract', 'version', 'standalone', 'product', 'capabilities', 'compatibility', 'runtime', 'source_adapters'] as $key) {
            if (($profile[$key] ?? null) !== $expected[$key]) {
                throw new InvalidArgumentException('NanoShell platform profile is invalid.');
            }
        }
    }
}
