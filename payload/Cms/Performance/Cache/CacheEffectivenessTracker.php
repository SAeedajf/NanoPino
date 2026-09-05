<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Cache;

final class CacheEffectivenessTracker
{
    private int $hits=0;
    private int $misses=0;
    private int $writes=0;

    public function hit(): void { $this->hits++; }
    public function miss(): void { $this->misses++; }
    public function write(): void { $this->writes++; }

    public function ratio(): ?float
    {
        $reads=$this->hits+$this->misses;
        return $reads===0 ? null : $this->hits/$reads;
    }

    /** @return array{hits:int,misses:int,writes:int,reads:int,hit_ratio:?float} */
    public function snapshot(): array
    {
        return [
            'hits'=>$this->hits,
            'misses'=>$this->misses,
            'writes'=>$this->writes,
            'reads'=>$this->hits+$this->misses,
            'hit_ratio'=>$this->ratio(),
        ];
    }
}
