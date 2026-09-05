<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

enum PolicyDecision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Abstain = 'abstain';
}
