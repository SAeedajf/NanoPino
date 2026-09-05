<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

final class InMemoryCacheTagClock implements CacheTagClockInterface
{
    /** @var array<string,int> */
    private array $generations=[];

    public function generation(string $tag): int
    {
        return $this->generations[$tag]??1;
    }

    public function bump(string $tag): int
    {
        return $this->generations[$tag]=($this->generations[$tag]??1)+1;
    }
}
