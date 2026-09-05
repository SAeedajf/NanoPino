<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

final class PerformanceSpan
{
    private int $startNs;
    private int $startMemory;
    private bool $stopped=false;

    /** @param array<string,mixed> $tags */
    public function __construct(
        public readonly string $name,
        public readonly PerformanceMetric $metric,
        private readonly PerformanceRecorderInterface $recorder,
        public readonly array $tags=[],
    ) {
        $this->startNs=hrtime(true);
        $this->startMemory=memory_get_usage(true);
    }

    public function stop(): PerformanceSample
    {
        if ($this->stopped) throw new \RuntimeException('Performance span already stopped.');
        $this->stopped=true;
        $elapsedMs=(hrtime(true)-$this->startNs)/1_000_000;

        $sample=new PerformanceSample(
            $this->name,
            $this->metric,
            $elapsedMs,
            microtime(true),
            $this->tags+[
                'memory_delta_bytes'=>memory_get_usage(true)-$this->startMemory,
                'memory_peak_bytes'=>memory_get_peak_usage(true),
            ],
        );
        $this->recorder->record($sample);
        return $sample;
    }
}
