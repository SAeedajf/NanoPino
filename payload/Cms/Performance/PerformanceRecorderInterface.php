<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

interface PerformanceRecorderInterface
{
    public function record(PerformanceSample $sample): void;

    /** @return list<PerformanceSample> */
    public function recent(int $limit=200): array;

    /** @return list<PerformanceSample> */
    public function forMetric(PerformanceMetric $metric,int $limit=200): array;
}
