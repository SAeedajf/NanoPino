<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

enum ExtensionOperationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case RecoveryRequired = 'recovery_required';

    public function terminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::RecoveryRequired], true);
    }
}
