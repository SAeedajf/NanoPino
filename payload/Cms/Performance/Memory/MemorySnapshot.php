<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Memory;

final readonly class MemorySnapshot
{
    public function __construct(
        public int $currentBytes,
        public int $peakBytes,
        public int $realCurrentBytes,
        public int $realPeakBytes,
    ) {}

    /** @return array<string,int> */
    public function toArray(): array
    {
        return [
            'current_bytes'=>$this->currentBytes,
            'peak_bytes'=>$this->peakBytes,
            'real_current_bytes'=>$this->realCurrentBytes,
            'real_peak_bytes'=>$this->realPeakBytes,
        ];
    }
}
