<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

enum WordPressThemeType: string
{
    case Block = 'block';
    case Classic = 'classic';
    case Hybrid = 'hybrid';
    case Unknown = 'unknown';
}
