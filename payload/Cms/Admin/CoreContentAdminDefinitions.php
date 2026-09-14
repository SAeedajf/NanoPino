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

        // Keep the content center reachable when the content group has nested
        // destinations. Luma renders a parent with children as an expandable
        // group and intentionally does not use the parent's route.
        $admin->menus->register(new AdminMenuDefinition(
            'cms.content.index',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.content_index'),
            'cms.content',
            'files',
            'content.read',
            'cms.content',
            'content',
            20,
        ));

        $admin->menus->register(new AdminMenuDefinition(
            'cms.taxonomies',
            CoreAdminDefinitions::OWNER,
            AdminI18n::text('nav.taxonomies'),
            'cms.taxonomies',
            'tags',
            'taxonomy.read',
            'cms.content',
            'content',
            25,
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

        $admin->routes->register(new AdminRouteDefinition(
            'cms.taxonomies',
            CoreAdminDefinitions::OWNER,
            '/taxonomies',
            'cms.taxonomies',
            'core:taxonomies',
            'taxonomy.read',
            [
                'title' => AdminI18n::text('routes.taxonomies.title'),
                'lead' => AdminI18n::text('routes.taxonomies.lead'),
            ],
            25,
        ));
    }
}
