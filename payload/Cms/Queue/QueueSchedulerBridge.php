<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

/**
 * Invokable bridge for Pinoox Scheduler.
 *
 * Scheduler is the clock; Queue remains the durable work state.
 */
final readonly class QueueSchedulerBridge
{
    public function __construct(
        private QueueWorker $worker,
        private int $batchSize=20,
    ) {}

    /** @return array{processed:int,completed:int,retried:int,dead:int} */
    public function __invoke(): array
    {
        return $this->worker->runBatch($this->batchSize);
    }
}
