<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

enum ScopeType: string
{
    case Global = 'global';
    case Site = 'site';
    case User = 'user';
    case Extension = 'extension';
    case Theme = 'theme';
}
