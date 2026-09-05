<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Manifest;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use InvalidArgumentException;

final readonly class ExtensionIdentity
{
    public function __construct(
        public string $identifier,
        public string $owner,
        public string $package,
        public ?string $targetApp = null,
        public ?string $themeName = null,
    ) {
        if ($identifier === '' || $owner === '' || $package === '') {
            throw new InvalidArgumentException('Extension identity fields cannot be empty.');
        }
    }

    public static function forApp(string $package): self
    {
        return new self($package, $package, $package);
    }

    public static function forTheme(string $targetApp, string $themeName): self
    {
        return new self(
            'theme:' . $targetApp . '/' . $themeName,
            $targetApp . '.theme.' . str_replace('-', '_', $themeName),
            $targetApp,
            $targetApp,
            $themeName,
        );
    }

    public static function fromPinx(array $pinx, ExtensionType $type): self
    {
        if ($type === ExtensionType::Theme) {
            return self::forTheme(
                (string) ($pinx['target_app'] ?? ''),
                (string) ($pinx['theme_name'] ?? $pinx['package'] ?? ''),
            );
        }

        return self::forApp((string) ($pinx['package'] ?? ''));
    }
}
