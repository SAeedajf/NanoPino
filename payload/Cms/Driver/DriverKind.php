<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Driver;

enum DriverKind: string
{
    case Search = 'search';
    case Cache = 'cache';
    case Queue = 'queue';
    case Storage = 'storage';
}
