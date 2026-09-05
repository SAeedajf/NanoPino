<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

enum BudgetStatus: string
{
    case Pass='pass';
    case Warning='warning';
    case Fail='fail';
    case Unmeasured='unmeasured';
}
