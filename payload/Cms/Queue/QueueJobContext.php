<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

final readonly class QueueJobContext
{
    public function __construct(
        public string $jobId,
        public int $attempt,
        public float $startedAt,
        public ?string $correlationId=null,
    ) {}
}
