<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Trash = 'trash';
}
