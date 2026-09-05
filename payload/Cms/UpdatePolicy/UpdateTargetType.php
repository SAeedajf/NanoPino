<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

enum UpdateTargetType: string
{
    case Extension = 'extension';
    case Cms = 'cms';
    case Pincore = 'pincore';
    case Platform = 'platform';
}
