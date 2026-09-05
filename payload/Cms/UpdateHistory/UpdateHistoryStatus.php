<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdateHistory;

enum UpdateHistoryStatus: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
    case RecoveryRequired = 'recovery_required';
}
