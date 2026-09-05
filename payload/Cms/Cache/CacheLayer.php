<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

enum CacheLayer: string
{
    case Object = 'object';
    case Query = 'query';
    case Page = 'page';
    case Api = 'api';
    case BuilderRender = 'builder_render';
}
