<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Posture;

enum SecurityControlStatus: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';

    public function rank(): int
    {
        return match($this) {
            self::Pass => 10,
            self::Warning => 20,
            self::Fail => 30,
        };
    }
}
