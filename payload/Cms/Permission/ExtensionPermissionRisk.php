<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Permission;

enum ExtensionPermissionRisk: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
    case Critical = 4;
}
