<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

final readonly class QueueDispatchResult
{
    public function __construct(
        public string $jobId,
        public QueueMode $mode,
        public QueueJobStatus $status,
        public bool $deduplicated=false,
    ) {}
}
