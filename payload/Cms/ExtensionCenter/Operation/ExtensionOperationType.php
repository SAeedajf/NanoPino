<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

enum ExtensionOperationType: string
{
    case Install = 'install';
    case Activate = 'activate';
    case Deactivate = 'deactivate';
    case Update = 'update';
    case Rollback = 'rollback';
    case Repair = 'repair';
    case Uninstall = 'uninstall';
}
