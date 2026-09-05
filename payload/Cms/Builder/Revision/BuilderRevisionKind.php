<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Revision;

enum BuilderRevisionKind: string
{
    case Initial = 'initial';
    case Manual = 'manual';
    case Autosave = 'autosave';
    case Published = 'published';
    case PreRestore = 'pre_restore';
    case Restored = 'restored';
}
