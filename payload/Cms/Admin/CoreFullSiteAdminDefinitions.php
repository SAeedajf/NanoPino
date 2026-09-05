<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreFullSiteAdminDefinitions
{
    public static function register(AdminRegistrySet $admin): void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.site_editor',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.site_editor'),
            'cms.site_editor',
            'panel-top',
            'builder.read',
            'cms.appearance',
            'site',
            52,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.site_editor',
            CoreAdminDefinitions::OWNER,
            '/appearance/site-editor',
            'cms.site_editor',
            'core:site-editor',
            'builder.read',
            [
                'title' => AdminI18n::text('routes.site_editor.title'),
                'lead' => AdminI18n::text('routes.site_editor.lead'),
            ],
            52,
        ));
    }
}
