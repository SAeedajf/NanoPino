<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

enum BuilderStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
