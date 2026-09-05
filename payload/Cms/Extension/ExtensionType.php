<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Extension;

enum ExtensionType: string
{
    case CoreModule = 'core-module';
    case Module = 'module';
    case Plugin = 'plugin';
    case Integration = 'integration';
    case Theme = 'theme';
    case AdminExtension = 'admin-extension';
    case Block = 'block';
    case BlockPackage = 'block-package';
    case Driver = 'driver';
    case LanguagePack = 'language-pack';

    public function isThemeTransport(): bool
    {
        return $this === self::Theme;
    }

    public function requiresBlocksProfile(): bool
    {
        return in_array($this, [self::Block, self::BlockPackage], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::CoreModule => 'Core Module',
            self::Module => 'Module',
            self::Plugin => 'Plugin',
            self::Integration => 'Integration',
            self::Theme => 'Theme',
            self::AdminExtension => 'Admin Extension',
            self::Block => 'Block',
            self::BlockPackage => 'Block Pack',
            self::Driver => 'Driver',
            self::LanguagePack => 'Language Pack',
        };
    }
}
