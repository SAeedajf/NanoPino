<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Search;

enum SearchApiErrorCode:string
{
    case InvalidRequest='search.invalid_request';
    case Forbidden='search.forbidden';
    case Unavailable='search.unavailable';
    case InternalError='search.internal_error';
}
