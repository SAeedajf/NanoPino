<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

enum MediaStatus: string
{
    case Ready = 'ready';
    case Quarantined = 'quarantined';
    case Deleted = 'deleted';
}
