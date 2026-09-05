<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

final readonly class QueueDispatcher
{
    public function __construct(
        private QueueRegistry $registry,
        private QueueRepositoryInterface $repository,
        private QueuePayloadValidator $payloads,
        private QueueMode $mode=QueueMode::Auto,
        private bool $asyncAvailable=false,
    ) {}

    /** @param array<string,mixed> $payload */
    public function dispatch(
        string $type,
        array $payload,
        ?string $dedupKey=null,
        ?string $correlationId=null,
        int $delaySeconds=0,
    ): QueueDispatchResult {
        $definition=$this->registry->job($type);
        $this->payloads->validate($payload,$definition->maxPayloadBytes);

        if ($dedupKey!==null) {
            if ($dedupKey==='' || strlen($dedupKey)>190) throw new \InvalidArgumentException('Invalid queue dedup key.');
            $existing=$this->repository->findActiveByDedupKey($dedupKey);
            if ($existing!==null) {
                return new QueueDispatchResult(
                    $existing->id,
                    $this->effectiveMode(),
                    $existing->status,
                    true,
                );
            }
        }

        $effective=$this->effectiveMode();
        $now=microtime(true);
        $job=new QueueEnvelope(
            'job-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(6)),
            $type,
            $payload,
            QueueJobStatus::Pending,
            0,
            $now+max(0,$delaySeconds),
            $now,
            $now,
            $dedupKey,
            $correlationId,
        );

        $job=$this->repository->push($job);

        if ($effective===QueueMode::Sync && $delaySeconds===0) {
            $worker=new QueueWorker($this->registry,$this->repository);
            $worker->runJob($job->id);
            $job=$this->repository->find($job->id) ?? $job;
        }

        return new QueueDispatchResult($job->id,$effective,$job->status);
    }

    public function effectiveMode(): QueueMode
    {
        if ($this->mode===QueueMode::Async && !$this->asyncAvailable) {
            throw new \RuntimeException('Async queue mode requested but no worker/scheduler runner is available.');
        }

        return match($this->mode) {
            QueueMode::Sync=>QueueMode::Sync,
            QueueMode::Async=>QueueMode::Async,
            QueueMode::Auto=>$this->asyncAvailable ? QueueMode::Async : QueueMode::Sync,
        };
    }
}
