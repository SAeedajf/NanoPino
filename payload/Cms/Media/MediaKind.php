<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
}
