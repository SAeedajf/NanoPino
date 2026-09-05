<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

enum QueueMode: string
{
    case Auto = 'auto';
    case Async = 'async';
    case Sync = 'sync';
}
