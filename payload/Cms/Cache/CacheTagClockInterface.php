<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

interface CacheTagClockInterface
{
    public function generation(string $tag): int;
    public function bump(string $tag): int;
}
