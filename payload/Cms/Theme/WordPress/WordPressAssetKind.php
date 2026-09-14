<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

enum WordPressAssetKind: string
{
    case Css = 'css';
    case JavaScript = 'javascript';
    case Font = 'font';
}
