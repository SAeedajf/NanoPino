<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Package;

enum StaticRiskSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case High = 'high';
}
