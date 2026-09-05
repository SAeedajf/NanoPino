<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;

final readonly class QueueControlService
{
    public function __construct(
        private AuthorizationManager $authorization,
        private QueueRepositoryInterface $repository,
    ) {}

    /** @return array<string,mixed> */
    public function status(?int $actorId=null): array
    {
        $this->authorization->authorize(new AuthorizationRequest('system.health.view',$actorId));
        return (new QueueMonitor($this->repository))->snapshot();
    }

    public function retry(string $jobId,?int $actorId=null): QueueEnvelope
    {
        $this->authorization->authorize(new AuthorizationRequest('system.queue.manage',$actorId));

        $job=$this->repository->find($jobId)
            ?? throw new \RuntimeException('Queue job not found.');

        if (!in_array($job->status,[QueueJobStatus::Failed,QueueJobStatus::Dead],true)) {
            throw new \RuntimeException('Only failed/dead queue jobs can be retried.');
        }

        $job->status=QueueJobStatus::Pending;
        $job->attempts=0;
        $job->availableAt=microtime(true);
        $job->lastError=null;
        $job->updatedAt=microtime(true);
        $this->repository->save($job);
        return $job;
    }
}
