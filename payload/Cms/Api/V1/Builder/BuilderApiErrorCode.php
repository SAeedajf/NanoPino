<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Builder;

enum BuilderApiErrorCode: string
{
    case InvalidRequest = 'builder.invalid_request';
    case Forbidden = 'builder.forbidden';
    case NotFound = 'builder.not_found';
    case VersionConflict = 'builder.version_conflict';
    case ValidationFailed = 'builder.validation_failed';
    case InternalError = 'builder.internal_error';
}
