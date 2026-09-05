<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

final class CoreMediaRevisionAdminDefinitions
{
    public static function register(AdminRegistrySet $admin): void
    {
        $admin->menus->register(new AdminMenuDefinition(
            'cms.media',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.media'),
            'cms.media',
            'images',
            'media.read',
            null,
            'content',
            30,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.media',
            CoreAdminDefinitions::OWNER,
            '/media',
            'cms.media',
            'core:media',
            'media.read',
            [
                'title' => AdminI18n::text('routes.media.title'),
                'lead' => AdminI18n::text('routes.media.lead'),
            ],
            30,
        ));

        $admin->menus->register(new AdminMenuDefinition(
            'cms.revisions',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.revisions'),
            'cms.revisions',
            'history',
            'content.read',
            'cms.content',
            'content',
            25,
        ));

        $admin->routes->register(new AdminRouteDefinition(
            'cms.revisions',
            CoreAdminDefinitions::OWNER,
            '/content/revisions',
            'cms.revisions',
            'core:revisions',
            'content.read',
            [
                'title' => AdminI18n::text('routes.revisions.title'),
                'lead' => AdminI18n::text('routes.revisions.lead'),
            ],
            25,
        ));
    }
}
