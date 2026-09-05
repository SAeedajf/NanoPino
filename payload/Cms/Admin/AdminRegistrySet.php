<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class AdminRegistrySet
{
    public readonly AdminMenuRegistry $menus;
    public readonly AdminRouteRegistry $routes;
    public readonly AdminWidgetRegistry $widgets;
    public readonly AdminPanelRegistry $panels;
    public readonly AdminComponentRegistry $components;

    public function __construct()
    {
        $this->menus = new AdminMenuRegistry();
        $this->routes = new AdminRouteRegistry();
        $this->widgets = new AdminWidgetRegistry();
        $this->panels = new AdminPanelRegistry();
        $this->components = new AdminComponentRegistry();
    }

    public function manifest(?callable $can = null): AdminManifest
    {
        $can ??= static fn (?string $permission): bool => true;
        $allowed = static fn (?string $permission): bool => $permission === null || (bool)$can($permission);

        $navigation = array_map(
            static fn (AdminMenuDefinition $item): array => $item->toArray(),
            array_values(array_filter($this->menus->definitions(), static fn (AdminMenuDefinition $item): bool => $allowed($item->permission)))
        );
        $routes = array_map(
            static fn (AdminRouteDefinition $item): array => $item->toArray(),
            array_values(array_filter($this->routes->definitions(), static fn (AdminRouteDefinition $item): bool => $allowed($item->permission)))
        );
        $widgets = array_map(
            static fn (AdminWidgetDefinition $item): array => $item->toArray(),
            array_values(array_filter($this->widgets->definitions(), static fn (AdminWidgetDefinition $item): bool => $allowed($item->permission)))
        );
        $panels = array_map(
            static fn (AdminPanelDefinition $item): array => $item->toArray(),
            array_values(array_filter($this->panels->definitions(), static fn (AdminPanelDefinition $item): bool => $allowed($item->permission)))
        );
        $components = array_map(
            static fn (AdminComponentDefinition $item): array => $item->toArray(),
            $this->components->definitions(),
        );

        return new AdminManifest($navigation, $routes, $widgets, $panels, $components);
    }

    public function removeOwner(string $owner): int
    {
        return $this->menus->removeOwner($owner)
            + $this->routes->removeOwner($owner)
            + $this->widgets->removeOwner($owner)
            + $this->panels->removeOwner($owner)
            + $this->components->removeOwner($owner);
    }

    /** @return array<string,list<array<string,mixed>>> */
    public function diagnostics(): array
    {
        return [
            'menus' => $this->menus->diagnostics(),
            'routes' => $this->routes->diagnostics(),
            'widgets' => $this->widgets->diagnostics(),
            'panels' => $this->panels->diagnostics(),
            'components' => $this->components->diagnostics(),
        ];
    }
}
