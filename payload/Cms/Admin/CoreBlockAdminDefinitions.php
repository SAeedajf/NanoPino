<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreBlockAdminDefinitions
{
    public static function register(AdminRegistrySet $admin): void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.blocks',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.blocks'),
            'cms.blocks',
            'blocks',
            'blocks.read',
            'cms.appearance',
            'manage',
            55,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.blocks',
            CoreAdminDefinitions::OWNER,
            '/appearance/blocks',
            'cms.blocks',
            'core:blocks',
            'blocks.read',
            [
                'title' => AdminI18n::text('routes.blocks.title'),
                'lead' => AdminI18n::text('routes.blocks.lead'),
            ],
            55,
        ));
    }
}
