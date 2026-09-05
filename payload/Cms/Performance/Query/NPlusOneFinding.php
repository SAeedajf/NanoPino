<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

final readonly class NPlusOneFinding
{
    public function __construct(
        public string $fingerprint,
        public int $count,
        public float $totalMs,
        public string $sampleSql,
    ) {}
}
