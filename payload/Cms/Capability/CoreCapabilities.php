<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Capability;

final class CoreCapabilities
{
    public const OWNER = 'cms.core';

    /** @return array<string,string> */
    public static function definitions(): array
    {
        return [
            'cms.admin' => 'Open the CMS administration shell.',

            'content.read' => 'Read content.',
            'content.create' => 'Create content.',
            'content.update' => 'Update content.',
            'content.delete' => 'Delete content.',
            'content.publish' => 'Publish content.',
            'content.manage_others' => 'Read or mutate content owned by another author.',
            'content.assign_author' => 'Assign content ownership to another user.',
            'taxonomy.read' => 'Read taxonomy terms.',
            'taxonomy.manage' => 'Create and manage taxonomy terms.',

            'media.read' => 'Read media library records.',
            'media.upload' => 'Upload media.',
            'media.update' => 'Update media metadata.',
            'media.delete' => 'Delete media.',

            'users.read' => 'View users and their public administrative profile.',
            'users.create' => 'Create users.',
            'users.update' => 'Update users.',
            'users.delete' => 'Delete users.',
            'users.roles.manage' => 'Assign and detach user roles.',
            'users.sessions.revoke' => 'Revoke user sessions.',

            'settings.read' => 'Read CMS settings.',
            'settings.manage' => 'Change CMS settings.',

            'extensions.read' => 'View installed extensions.',
            'extensions.manage' => 'Access extension management workspace.',
            'extensions.install' => 'Install extensions.',
            'extensions.activate' => 'Activate extensions.',
            'extensions.deactivate' => 'Deactivate extensions.',
            'extensions.update' => 'Update extensions.',
            'extensions.uninstall' => 'Uninstall extensions.',
            'extensions.repair' => 'Repair extensions.',

            'themes.read' => 'View themes.',
            'themes.preview' => 'Preview installed themes without changing the active theme.',
            'themes.customize' => 'Change theme design settings and style variations.',
            'themes.install' => 'Install themes.',
            'themes.activate' => 'Activate themes.',
            'themes.update' => 'Update themes.',
            'themes.uninstall' => 'Uninstall themes.',

            'blocks.read' => 'Read the block catalog.',
            'blocks.use' => 'Use registered blocks in builder documents.',
            'blocks.manage' => 'Manage block packages and block registrations.',

            'builder.read' => 'Read site-builder documents and history.',
            'builder.preview' => 'Preview validated site-builder drafts.',
            'builder.edit' => 'Edit site-builder documents.',
            'builder.publish' => 'Publish site-builder documents.',

            'audit.read' => 'View audit records.',
            'system.view' => 'View system status.',
            'system.health.view' => 'View detailed system health.',
            'system.recovery' => 'Use recovery points and Safe Mode.',
            'system.update' => 'Update the CMS/platform through approved flows.',
            'system.cache.manage' => 'Invalidate CMS semantic cache layers and tags.',
            'system.queue.manage' => 'Retry and manage failed CMS queue jobs.',
            'system.search.manage' => 'Manage CMS search indexing operations.',
            'system.security.view' => 'View CMS security posture and control status.',
            'system.security.manage' => 'Change approved CMS security policies and bindings.',
            'system.performance.view' => 'View CMS performance budgets and telemetry.',
            'system.performance.manage' => 'Manage approved performance profiling and benchmark operations.',
            'system.logs.view' => 'View privacy-safe CMS runtime logs.',
            'system.support.export' => 'Export privacy-safe CMS support diagnostics.',
            'system.developer.view' => 'View CMS Developer SDK and Extension contracts.',
        ];
    }

    public static function register(CapabilityRegistry $registry): void
    {
        foreach (self::definitions() as $key => $description) {
            $registry->register(new CapabilityDefinition($key, self::OWNER, $description));
        }
    }
}
