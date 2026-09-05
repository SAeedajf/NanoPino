<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreThemeAdminDefinitions
{
    public static function register(AdminRegistrySet $admin): void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.appearance',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.appearance'),
            'cms.appearance',
            'palette',
            'themes.read',
            null,
            'manage',
            50,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.appearance',
            CoreAdminDefinitions::OWNER,
            '/appearance',
            'cms.appearance',
            'core:appearance',
            'themes.read',
            [
                'title' => AdminI18n::text('routes.appearance.title'),
                'lead' => AdminI18n::text('routes.appearance.lead'),
            ],
            50,
        ));
    }
}
