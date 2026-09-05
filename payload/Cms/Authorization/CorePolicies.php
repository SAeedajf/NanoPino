<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

final class CorePolicies
{
    public const OWNER = 'cms.core';

    public static function register(PolicyRegistry $registry): void
    {
        // Self-management operations may be extended later; no broad hidden policy is
        // registered here. Resource domains register their own policies when those
        // domains are introduced, preserving ownership and avoiding premature coupling.
    }
}
