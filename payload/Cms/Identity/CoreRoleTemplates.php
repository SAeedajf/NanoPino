<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

final class CoreRoleTemplates
{
    public const OWNER = 'cms.core';

    public static function register(RoleTemplateRegistry $registry): void
    {
        foreach ([
            new RoleTemplateDefinition(
                'cms_editor',
                self::OWNER,
                'Editor',
                'Manage and publish site content without system/extension administration.',
                ['cms.admin', 'content.*', 'taxonomy.*', 'media.read', 'media.upload', 'media.update', 'blocks.read', 'blocks.use', 'builder.read', 'builder.preview', 'builder.edit', 'builder.publish'],
            ),
            new RoleTemplateDefinition(
                'cms_author',
                self::OWNER,
                'Author',
                'Create content and manage own work. Resource ownership is enforced by policies.',
                ['cms.admin', 'content.read', 'content.create', 'content.update', 'taxonomy.read', 'media.read', 'media.upload'],
            ),
            new RoleTemplateDefinition(
                'cms_media_manager',
                self::OWNER,
                'Media Manager',
                'Manage the media library.',
                ['cms.admin', 'media.*'],
            ),
            new RoleTemplateDefinition(
                'cms_site_manager',
                self::OWNER,
                'Site Manager',
                'Manage site content, media, appearance and settings without platform update authority.',
                [
                    'cms.admin',
                    'content.*',
                    'taxonomy.*',
                    'media.*',
                    'blocks.read',
                    'blocks.use',
                    'builder.*',
                    'themes.read',
                    'themes.preview',
                    'themes.customize',
                    'themes.activate',
                    'settings.read',
                    'settings.manage',
                    'users.read',
                ],
            ),
        ] as $definition) {
            $registry->register($definition);
        }
    }
}
