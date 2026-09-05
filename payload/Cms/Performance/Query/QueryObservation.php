<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

final readonly class QueryObservation
{
    public function __construct(
        public string $sql,
        public float $durationMs,
        public float $recordedAt,
    ) {
        if ($durationMs < 0 || strlen($sql) > 1_000_000) {
            throw new \InvalidArgumentException('Invalid query observation.');
        }
    }
}
