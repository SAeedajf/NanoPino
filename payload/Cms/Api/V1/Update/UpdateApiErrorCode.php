<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Update;

enum UpdateApiErrorCode: string
{
    case InvalidRequest = 'update.invalid_request';
    case Forbidden = 'update.forbidden';
    case NotFound = 'update.not_found';
    case Conflict = 'update.conflict';
    case PolicyRejected = 'update.policy_rejected';
    case RecoveryRequired = 'update.recovery_required';
    case InternalError = 'update.internal_error';
}
