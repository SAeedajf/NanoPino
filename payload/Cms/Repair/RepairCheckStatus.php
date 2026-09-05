<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Repair;

enum RepairCheckStatus: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';
}
