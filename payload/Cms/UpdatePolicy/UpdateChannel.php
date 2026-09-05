<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

enum UpdateChannel: string
{
    case Stable = 'stable';
    case Beta = 'beta';
    case Development = 'development';

    public function riskRank(): int
    {
        return match ($this) {
            self::Stable => 10,
            self::Beta => 20,
            self::Development => 30,
        };
    }
}
