<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

enum ExtensionCenterStatus: string
{
    case Installed = 'installed';
    case Active = 'active';
    case Inactive = 'inactive';
    case Updating = 'updating';
    case Failed = 'failed';
    case Quarantined = 'quarantined';
    case Incompatible = 'incompatible';
}
