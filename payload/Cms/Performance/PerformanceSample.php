<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

final readonly class PerformanceSample
{
    /** @param array<string,mixed> $tags */
    public function __construct(
        public string $name,
        public PerformanceMetric $metric,
        public float $value,
        public float $recordedAt,
        public array $tags = [],
    ) {
        if ($name === '' || strlen($name) > 190) {
            throw new \InvalidArgumentException('Invalid performance sample name.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name'=>$this->name,
            'metric'=>$this->metric->value,
            'value'=>$this->value,
            'recorded_at'=>$this->recordedAt,
            'tags'=>$this->tags,
        ];
    }
}
