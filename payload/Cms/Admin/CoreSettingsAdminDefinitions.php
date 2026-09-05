<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreSettingsAdminDefinitions
{
    public static function register(AdminRegistrySet $admin): void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.settings',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.settings'),
            'cms.settings',
            'settings',
            'settings.read',
            null,
            'system',
            80,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.settings',
            CoreAdminDefinitions::OWNER,
            '/settings',
            'cms.settings',
            'core:settings',
            'settings.read',
            [
                'title' => AdminI18n::text('routes.settings.title'),
                'lead' => AdminI18n::text('routes.settings.lead'),
            ],
            80,
        ));

        $admin->menus->register(new AdminMenuDefinition(
            'cms.audit',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.audit'),
            'cms.audit',
            'scroll-text',
            'audit.read',
            'cms.system',
            'system',
            110,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.audit',
            CoreAdminDefinitions::OWNER,
            '/system/audit',
            'cms.audit',
            'core:audit',
            'audit.read',
            [
                'title' => AdminI18n::text('routes.audit.title'),
                'lead' => AdminI18n::text('routes.audit.lead'),
            ],
            110,
        ));
    }
}
