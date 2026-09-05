<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

interface ThemeActivationGatewayInterface
{
    public function active(string $package, ?string $context = null): ?string;

    /**
     * Persistently selects a theme for the Pinoox app/context.
     * Production implementation must use Pinoox config/lifecycle infrastructure.
     */
    public function activate(string $package, string $themeName, ?string $context = null): void;
}
