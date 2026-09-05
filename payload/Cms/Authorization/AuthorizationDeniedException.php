<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

use RuntimeException;

final class AuthorizationDeniedException extends RuntimeException
{
    public function __construct(public readonly AuthorizationResult $result)
    {
        parent::__construct('CMS authorization denied: ' . $result->reason);
    }
}
