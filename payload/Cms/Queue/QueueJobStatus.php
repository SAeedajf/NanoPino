<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

enum QueueJobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Dead = 'dead';
}
