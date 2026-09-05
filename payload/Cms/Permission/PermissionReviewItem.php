<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Permission;

final readonly class PermissionReviewItem
{
    public function __construct(
        public string $permission,
        public bool $known,
        public ExtensionPermissionRisk $risk,
        public string $description,
    ) {
    }
}
