<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Permission;

final class CoreExtensionPermissions
{
    public static function register(ExtensionPermissionRegistry $registry): void
    {
        $items = [
            ['content.read', 'Read CMS content.', ExtensionPermissionRisk::Low],
            ['content.create', 'Create CMS content.', ExtensionPermissionRisk::Medium],
            ['content.update', 'Modify CMS content.', ExtensionPermissionRisk::Medium],
            ['content.publish', 'Publish CMS content.', ExtensionPermissionRisk::High],
            ['content.delete', 'Delete CMS content.', ExtensionPermissionRisk::High],
            ['content.manage_others', 'Read or mutate content owned by other authors.', ExtensionPermissionRisk::High],
            ['content.assign_author', 'Assign CMS content ownership to another user.', ExtensionPermissionRisk::High],
            ['media.read', 'Read media metadata/files.', ExtensionPermissionRisk::Low],
            ['media.upload', 'Upload media.', ExtensionPermissionRisk::Medium],
            ['media.delete', 'Delete media.', ExtensionPermissionRisk::High],
            ['users.read', 'Read user information.', ExtensionPermissionRisk::High],
            ['users.manage', 'Create or modify users and access.', ExtensionPermissionRisk::Critical],
            ['settings.read', 'Read CMS settings.', ExtensionPermissionRisk::Medium],
            ['settings.write', 'Modify CMS settings.', ExtensionPermissionRisk::High],
            ['network.external', 'Make outbound network requests.', ExtensionPermissionRisk::High],
            ['filesystem.storage', 'Use CMS-managed storage.', ExtensionPermissionRisk::High],
            ['scheduler.register', 'Register scheduled tasks.', ExtensionPermissionRisk::Medium],
            ['admin.menu', 'Add admin navigation items.', ExtensionPermissionRisk::Low],
            ['admin.route', 'Add admin routes/pages.', ExtensionPermissionRisk::Medium],
            ['admin.widget', 'Add dashboard widgets.', ExtensionPermissionRisk::Low],
            ['api.register', 'Register API endpoints.', ExtensionPermissionRisk::Medium],
            ['system.health', 'Publish health checks.', ExtensionPermissionRisk::Low],
            ['system.update', 'Participate in system update operations.', ExtensionPermissionRisk::Critical],
            ['extensions.install', 'Install or modify other extensions.', ExtensionPermissionRisk::Critical],
            ['themes.install', 'Install or activate themes.', ExtensionPermissionRisk::High],
            ['themes.extend', 'Register theme templates, design integrations or appearance UI.', ExtensionPermissionRisk::Medium],
            ['blocks.register', 'Register block definitions, renderers or migrations.', ExtensionPermissionRisk::Medium],
            ['blocks.render', 'Participate in server-side block rendering.', ExtensionPermissionRisk::High],
            ['builder.extend', 'Register Builder data sources, inspector panels or editor integrations.', ExtensionPermissionRisk::Medium],
        ];

        foreach ($items as [$id, $description, $risk]) {
            if (!$registry->has($id)) {
                $registry->register(new ExtensionPermissionDefinition($id, 'cms.core', $description, $risk));
            }
        }
    }
}
