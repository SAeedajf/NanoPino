<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Benchmark;

final readonly class BenchmarkResult
{
    /** @param list<float> $samplesMs */
    public function __construct(
        public string $name,
        public int $iterations,
        public array $samplesMs,
        public float $minMs,
        public float $maxMs,
        public float $meanMs,
        public float $p50Ms,
        public float $p95Ms,
        public float $p99Ms,
        public int $memoryPeakBytes,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name'=>$this->name,
            'iterations'=>$this->iterations,
            'min_ms'=>$this->minMs,
            'max_ms'=>$this->maxMs,
            'mean_ms'=>$this->meanMs,
            'p50_ms'=>$this->p50Ms,
            'p95_ms'=>$this->p95Ms,
            'p99_ms'=>$this->p99Ms,
            'memory_peak_bytes'=>$this->memoryPeakBytes,
        ];
    }
}
