<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreDeveloperAdminDefinitions
{
    public const OWNER='cms.core';

    public static function register(AdminRegistrySet $admin):void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.developer.sdk',
            self::OWNER,
            AdminI18n::text('nav.developer_sdk'),
            'cms.developer.sdk',
            'braces',
            'system.developer.view',
            null,
            'system',
            150,
        ));
        $admin->routes->register(new AdminRouteDefinition(
            'cms.developer.sdk',
            self::OWNER,
            '/developer/sdk',
            'cms.developer.sdk',
            'core:developer-sdk',
            'system.developer.view',
            [
                'title'=>AdminI18n::text('routes.developer_sdk.title'),
                'lead'=>AdminI18n::text('routes.developer_sdk.lead'),
            ],
            150,
        ));
    }
}
