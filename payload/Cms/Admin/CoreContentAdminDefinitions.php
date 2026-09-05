<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreContentAdminDefinitions
{
    public static function register(AdminRegistrySet $admin): void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.content',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.content'),
            'cms.content',
            'files',
            'content.read',
            null,
            'content',
            20,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.content',
            CoreAdminDefinitions::OWNER,
            '/content',
            'cms.content',
            'core:content',
            'content.read',
            [
                'title' => AdminI18n::text('routes.content.title'),
                'lead' => AdminI18n::text('routes.content.lead'),
            ],
            20,
        ));
    }
}
