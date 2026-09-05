<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreIdentityAdminDefinitions
{
    public static function register(AdminRegistrySet $admin): void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.users',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.users'),
            'cms.users',
            'users',
            'users.read',
            null,
            'manage',
            70,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.users',
            CoreAdminDefinitions::OWNER,
            '/users',
            'cms.users',
            'core:users',
            'users.read',
            [
                'title' => AdminI18n::text('routes.users.title'),
                'lead' => AdminI18n::text('routes.users.lead'),
            ],
            70,
        ));
    }
}
