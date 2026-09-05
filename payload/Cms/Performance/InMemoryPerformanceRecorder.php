<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

final class InMemoryPerformanceRecorder implements PerformanceRecorderInterface
{
    /** @var list<PerformanceSample> */
    private array $samples=[];

    public function record(PerformanceSample $sample): void
    {
        $this->samples[]=$sample;
        if (count($this->samples)>5000) {
            $this->samples=array_slice($this->samples,-5000);
        }
    }

    public function recent(int $limit=200): array
    {
        return array_slice(array_reverse($this->samples),0,max(1,min(1000,$limit)));
    }

    public function forMetric(PerformanceMetric $metric,int $limit=200): array
    {
        return array_slice(array_values(array_filter(
            array_reverse($this->samples),
            static fn(PerformanceSample $s):bool=>$s->metric===$metric,
        )),0,max(1,min(1000,$limit)));
    }
}
