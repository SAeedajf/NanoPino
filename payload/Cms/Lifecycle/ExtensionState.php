<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Lifecycle;

enum ExtensionState: string
{
    case Discovered = 'discovered';
    case Validated = 'validated';
    case Installing = 'installing';
    case Installed = 'installed';
    case Activating = 'activating';
    case Active = 'active';
    case Deactivating = 'deactivating';
    case Inactive = 'inactive';
    case Updating = 'updating';
    case Repairing = 'repairing';
    case Uninstalling = 'uninstalling';
    case Removed = 'removed';
    case Failed = 'failed';
    case Rollback = 'rollback';
}
