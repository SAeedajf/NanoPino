<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Permission;

use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;

final readonly class ExtensionPermissionReviewer
{
    public function __construct(private ExtensionPermissionRegistry $registry)
    {
    }

    public function review(ExtensionManifest $manifest): PermissionReview
    {
        $items = [];
        foreach ($manifest->permissions() as $permission) {
            $definition = $this->registry->permission($permission);
            if ($definition === null) {
                $items[] = new PermissionReviewItem(
                    $permission,
                    false,
                    ExtensionPermissionRisk::Critical,
                    'Unknown extension permission; a registered permission contract is required.',
                );
                continue;
            }
            $items[] = new PermissionReviewItem(
                $permission,
                true,
                $definition->risk,
                $definition->description,
            );
        }
        return new PermissionReview($items);
    }
}
