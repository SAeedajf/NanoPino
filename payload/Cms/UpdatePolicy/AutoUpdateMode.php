<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

enum AutoUpdateMode: string
{
    case Disabled = 'disabled';
    case SecurityOnly = 'security_only';
    case PatchOnly = 'patch_only';
    case Enabled = 'enabled';
}
