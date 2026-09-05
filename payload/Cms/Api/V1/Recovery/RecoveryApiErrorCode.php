<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Recovery;

enum RecoveryApiErrorCode: string
{
    case InvalidRequest = 'recovery.invalid_request';
    case Forbidden = 'recovery.forbidden';
    case NotFound = 'recovery.not_found';
    case HealthBlocked = 'recovery.health_blocked';
    case RestoreFailed = 'recovery.restore_failed';
    case InternalError = 'recovery.internal_error';
}
