<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreBuilderAdminDefinitions
{
    public static function register(AdminRegistrySet $admin): void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.builder',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.builder'),
            'cms.builder',
            'panels-top-left',
            'builder.read',
            null,
            'site',
            45,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.builder',
            CoreAdminDefinitions::OWNER,
            '/builder',
            'cms.builder',
            'core:builder',
            'builder.read',
            [
                'title' => AdminI18n::text('routes.builder.title'),
                'lead' => AdminI18n::text('routes.builder.lead'),
            ],
            45,
        ));
    }
}
