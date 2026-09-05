<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

enum AbilitySource: string
{
    case Admin = 'admin';
    case Api = 'api';
    case Cli = 'cli';
    case Automation = 'automation';
    case Ai = 'ai';
    case Internal = 'internal';
}
