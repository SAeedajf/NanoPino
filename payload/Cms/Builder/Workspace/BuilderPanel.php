<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Workspace;

enum BuilderPanel: string
{
    case Blocks = 'blocks';
    case Layers = 'layers';
    case Inspector = 'inspector';
    case History = 'history';
}
