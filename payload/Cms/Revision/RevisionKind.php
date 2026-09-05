<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

enum RevisionKind: string
{
    case Initial = 'initial';
    case Manual = 'manual';
    case Autosave = 'autosave';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case PreRestore = 'pre_restore';
    case Restored = 'restored';
}
