<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Release;

final readonly class CanaryHealthSnapshot
{
    public function __construct(
        public int $windowSeconds,
        public int $sampleCount,
        public int $errorCount,
        public float $availabilityPercent,
        public float $p95LatencyMs,
        public int $criticalIncidents,
        public bool $healthChecksPassed,
        public bool $rollbackRequested = false,
    ) {
        if ($this->windowSeconds < 1) {
            throw new \InvalidArgumentException('Canary window must be positive.');
        }
        if ($this->sampleCount < 1) {
            throw new \InvalidArgumentException('Canary sample count must be positive.');
        }
        if ($this->errorCount < 0 || $this->errorCount > $this->sampleCount) {
            throw new \InvalidArgumentException('Canary error count is outside the sample range.');
        }
        if ($this->availabilityPercent < 0 || $this->availabilityPercent > 100) {
            throw new \InvalidArgumentException('Canary availability must be between 0 and 100.');
        }
        if ($this->p95LatencyMs < 0) {
            throw new \InvalidArgumentException('Canary latency cannot be negative.');
        }
        if ($this->criticalIncidents < 0) {
            throw new \InvalidArgumentException('Canary incident count cannot be negative.');
        }
    }

    public function errorRatePercent(): float
    {
        return ($this->errorCount / $this->sampleCount) * 100;
    }

    /** @return array<string,int|float|bool> */
    public function toArray(): array
    {
        return [
            'window_seconds' => $this->windowSeconds,
            'sample_count' => $this->sampleCount,
            'error_count' => $this->errorCount,
            'error_rate_percent' => round($this->errorRatePercent(), 4),
            'availability_percent' => $this->availabilityPercent,
            'p95_latency_ms' => $this->p95LatencyMs,
            'critical_incidents' => $this->criticalIncidents,
            'health_checks_passed' => $this->healthChecksPassed,
            'rollback_requested' => $this->rollbackRequested,
        ];
    }
}
