<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

enum MediaVariantMode: string
{
    case ScaleDown = 'scale_down';
    case CoverDown = 'cover_down';
    case Crop = 'crop';
}
