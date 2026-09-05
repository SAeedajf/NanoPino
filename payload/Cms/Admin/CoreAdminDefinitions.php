<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreAdminDefinitions
{
    public const OWNER = 'cms.core';

    public static function register(AdminRegistrySet $admin): void
    {
        foreach ([
            new AdminMenuDefinition('cms.dashboard', self::OWNER, AdminI18n::text('nav.dashboard'), 'cms.dashboard', 'layout-dashboard', null, null, 'overview', 10),
            new AdminMenuDefinition('cms.extensions', self::OWNER, AdminI18n::text('nav.extensions'), 'cms.extensions', 'blocks', 'extensions.manage', null, 'manage', 60),
            new AdminMenuDefinition('cms.updates', self::OWNER, AdminI18n::text('nav.updates'), 'cms.updates', 'refresh-cw', 'extensions.update', 'cms.extensions', 'manage', 65),
            new AdminMenuDefinition('cms.system', self::OWNER, AdminI18n::text('nav.system'), 'cms.system', 'activity', 'system.view', null, 'system', 90),
            new AdminMenuDefinition('cms.recovery', self::OWNER, AdminI18n::text('nav.recovery'), 'cms.recovery', 'shield-check', 'system.recovery', 'cms.system', 'system', 100),
            new AdminMenuDefinition('cms.infrastructure', self::OWNER, AdminI18n::text('nav.infrastructure'), 'cms.infrastructure', 'server-cog', 'system.health.view', 'cms.system', 'system', 110),
            new AdminMenuDefinition('cms.security', self::OWNER, AdminI18n::text('nav.security'), 'cms.security', 'shield', 'system.security.view', 'cms.system', 'system', 120),
            new AdminMenuDefinition('cms.performance', self::OWNER, AdminI18n::text('nav.performance'), 'cms.performance', 'gauge', 'system.performance.view', 'cms.system', 'system', 130),
            new AdminMenuDefinition('cms.logs', self::OWNER, AdminI18n::text('nav.logs'), 'cms.logs', 'scroll-text', 'system.logs.view', 'cms.system', 'system', 140),
        ] as $definition) {
            $admin->menus->register($definition);
        }

        foreach ([
            new AdminRouteDefinition('cms.dashboard', self::OWNER, '/', 'cms.dashboard', 'core:dashboard', null, ['title' => AdminI18n::text('routes.dashboard.title'), 'lead' => AdminI18n::text('routes.dashboard.lead')], 10),
            new AdminRouteDefinition('cms.extensions', self::OWNER, '/extensions', 'cms.extensions', 'core:extensions', 'extensions.manage', ['title' => AdminI18n::text('routes.extensions.title'), 'lead' => AdminI18n::text('routes.extensions.lead')], 60),
            new AdminRouteDefinition('cms.updates', self::OWNER, '/extensions/updates', 'cms.updates', 'core:updates', 'extensions.update', ['title' => AdminI18n::text('routes.updates.title'), 'lead' => AdminI18n::text('routes.updates.lead')], 65),
            new AdminRouteDefinition('cms.recovery', self::OWNER, '/system/recovery', 'cms.recovery', 'core:recovery', 'system.recovery', ['title' => AdminI18n::text('routes.recovery.title'), 'lead' => AdminI18n::text('routes.recovery.lead')], 100),
            new AdminRouteDefinition('cms.system', self::OWNER, '/system', 'cms.system', 'core:system', 'system.view', ['title' => AdminI18n::text('routes.system.title'), 'lead' => AdminI18n::text('routes.system.lead')], 90),
            new AdminRouteDefinition('cms.infrastructure', self::OWNER, '/system/infrastructure', 'cms.infrastructure', 'core:infrastructure', 'system.health.view', ['title' => AdminI18n::text('routes.infrastructure.title'), 'lead' => AdminI18n::text('routes.infrastructure.lead')], 110),
            new AdminRouteDefinition('cms.security', self::OWNER, '/system/security', 'cms.security', 'core:security', 'system.security.view', ['title' => AdminI18n::text('routes.security.title'), 'lead' => AdminI18n::text('routes.security.lead')], 120),
            new AdminRouteDefinition('cms.performance', self::OWNER, '/system/performance', 'cms.performance', 'core:performance', 'system.performance.view', ['title' => AdminI18n::text('routes.performance.title'), 'lead' => AdminI18n::text('routes.performance.lead')], 130),
            new AdminRouteDefinition('cms.logs', self::OWNER, '/system/logs', 'cms.logs', 'core:logs', 'system.logs.view', ['title' => AdminI18n::text('routes.logs.title'), 'lead' => AdminI18n::text('routes.logs.lead')], 140),
        ] as $definition) {
            $admin->routes->register($definition);
        }

        foreach ([
            new AdminWidgetDefinition('cms.widget.health', self::OWNER, 'dashboard.primary', 'core:health-summary', 'system.view', 10),
            new AdminWidgetDefinition('cms.widget.extensions', self::OWNER, 'dashboard.primary', 'core:extension-summary', 'extensions.manage', 20),
            new AdminWidgetDefinition('cms.widget.recovery', self::OWNER, 'dashboard.secondary', 'core:recovery-summary', 'system.recovery', 10),
        ] as $definition) {
            $admin->widgets->register($definition);
        }
    }
}
