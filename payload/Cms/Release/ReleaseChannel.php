<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Release;

enum ReleaseChannel: string
{
    case Canary = 'canary';
    case Stable = 'stable';
}
