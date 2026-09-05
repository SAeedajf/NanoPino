<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

use App\com_pinoox_cms\Cms\Security\Secrets\SensitiveDataRedactor;

final readonly class PerformanceProfiler
{
    public function __construct(
        private PerformanceRecorderInterface $recorder,
        private SensitiveDataRedactor $redactor=new SensitiveDataRedactor(),
    ) {}

    /** @param array<string,mixed> $tags */
    public function start(string $name,PerformanceMetric $metric,array $tags=[]): PerformanceSpan
    {
        $safe=$this->redactor->redact($tags);
        return new PerformanceSpan(
            $name,
            $metric,
            $this->recorder,
            is_array($safe) ? $safe : [],
        );
    }

    /** @template T @param callable():T $callback @param array<string,mixed> $tags @return T */
    public function measure(string $name,PerformanceMetric $metric,callable $callback,array $tags=[]): mixed
    {
        $span=$this->start($name,$metric,$tags);
        try {
            return $callback();
        } finally {
            $span->stop();
        }
    }

    public function gauge(string $name,PerformanceMetric $metric,float $value,array $tags=[]): PerformanceSample
    {
        $safe=$this->redactor->redact($tags);
        $sample=new PerformanceSample(
            $name,
            $metric,
            $value,
            microtime(true),
            is_array($safe) ? $safe : [],
        );
        $this->recorder->record($sample);
        return $sample;
    }
}
