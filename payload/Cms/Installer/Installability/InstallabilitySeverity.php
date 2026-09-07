<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer\Installability;

enum InstallabilitySeverity: string
{
    case Blocker = 'blocker';
    case Warning = 'warning';
}
