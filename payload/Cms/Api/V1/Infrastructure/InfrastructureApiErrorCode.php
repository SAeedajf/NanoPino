<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Infrastructure;

enum InfrastructureApiErrorCode:string
{
    case InvalidRequest='infrastructure.invalid_request';
    case Forbidden='infrastructure.forbidden';
    case NotFound='infrastructure.not_found';
    case Conflict='infrastructure.conflict';
    case InternalError='infrastructure.internal_error';
}
