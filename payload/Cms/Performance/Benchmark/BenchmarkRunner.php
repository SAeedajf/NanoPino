<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Benchmark;

final class BenchmarkRunner
{
    public function run(
        string $name,
        callable $callback,
        int $iterations=20,
        int $warmups=3,
    ): BenchmarkResult {
        if ($iterations<1 || $iterations>10000 || $warmups<0 || $warmups>1000) {
            throw new \InvalidArgumentException('Invalid benchmark iteration count.');
        }

        for ($i=0;$i<$warmups;$i++) $callback();

        $samples=[];
        $startPeak=memory_get_peak_usage(true);

        for ($i=0;$i<$iterations;$i++) {
            $start=hrtime(true);
            $callback();
            $samples[]=(hrtime(true)-$start)/1_000_000;
        }

        sort($samples,SORT_NUMERIC);
        $mean=array_sum($samples)/count($samples);

        return new BenchmarkResult(
            $name,
            $iterations,
            $samples,
            $samples[0],
            $samples[count($samples)-1],
            $mean,
            $this->percentile($samples,0.50),
            $this->percentile($samples,0.95),
            $this->percentile($samples,0.99),
            max($startPeak,memory_get_peak_usage(true)),
        );
    }

    /** @param list<float> $sorted */
    private function percentile(array $sorted,float $p): float
    {
        $index=(int)ceil($p*count($sorted))-1;
        $index=max(0,min(count($sorted)-1,$index));
        return $sorted[$index];
    }
}
