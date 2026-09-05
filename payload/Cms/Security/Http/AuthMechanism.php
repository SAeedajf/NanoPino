<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

enum AuthMechanism: string
{
    case Anonymous = 'anonymous';
    case Session = 'session';
    case Bearer = 'bearer';
    case Hmac = 'hmac';
    case Internal = 'internal';
}
