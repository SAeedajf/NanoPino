<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

enum RecoveryPointStatus: string
{
    case Creating = 'creating';
    case Ready = 'ready';
    case Restoring = 'restoring';
    case Restored = 'restored';
    case Failed = 'failed';
    case Expired = 'expired';
}
