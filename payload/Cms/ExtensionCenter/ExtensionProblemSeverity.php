<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

enum ExtensionProblemSeverity: int
{
    case Info = 10;
    case Warning = 20;
    case Error = 30;
    case Critical = 40;
}
