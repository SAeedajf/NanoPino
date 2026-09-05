<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Extension;

enum ExtensionApiErrorCode: string
{
    case InvalidRequest = 'extension.invalid_request';
    case Forbidden = 'extension.forbidden';
    case NotFound = 'extension.not_found';
    case Conflict = 'extension.conflict';
    case DependencyFailed = 'extension.dependency_failed';
    case ReviewRequired = 'extension.permission_review_required';
    case TrustFailed = 'extension.trust_failed';
    case OperationFailed = 'extension.operation_failed';
    case RecoveryRequired = 'extension.recovery_required';
    case InternalError = 'extension.internal_error';
}
