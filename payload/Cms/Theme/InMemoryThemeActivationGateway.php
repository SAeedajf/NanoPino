<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

final class InMemoryThemeActivationGateway implements ThemeActivationGatewayInterface
{
    /** @var array<string,string> */
    private array $active = [];

    public function active(string $package, ?string $context = null): ?string
    {
        return $this->active[$this->key($package, $context)] ?? null;
    }

    public function activate(string $package, string $themeName, ?string $context = null): void
    {
        $this->active[$this->key($package, $context)] = $themeName;
    }

    private function key(string $package, ?string $context): string
    {
        return $package . ':' . ($context ?? 'default');
    }
}
