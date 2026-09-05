<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Workspace;

enum BuilderViewport: string
{
    case Desktop = 'desktop';
    case Tablet = 'tablet';
    case Mobile = 'mobile';
}
