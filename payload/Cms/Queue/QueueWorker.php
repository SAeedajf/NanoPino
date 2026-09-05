<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

use App\com_pinoox_cms\Cms\Performance\PerformanceMetric;
use App\com_pinoox_cms\Cms\Performance\PerformanceProfiler;

final readonly class QueueWorker
{
    public function __construct(
        private QueueRegistry $registry,
        private QueueRepositoryInterface $repository,
        private ?PerformanceProfiler $performance=null,
    ) {}

    /** @return array{processed:int,completed:int,retried:int,dead:int} */
    public function runBatch(int $limit=20): array
    {
        $limit=max(1,min(500,$limit));
        $stats=['processed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];

        for ($i=0;$i<$limit;$i++) {
            $job=$this->repository->reserve();
            if ($job===null) break;
            $result=$this->executeReserved($job);
            $stats['processed']++;
            $stats[$result]++;
        }

        return $stats;
    }

    public function runJob(string $jobId): QueueJobStatus
    {
        $existing=$this->repository->find($jobId)
            ?? throw new \RuntimeException('Queue job not found.');

        $job=$this->repository->claim($jobId);
        if ($job===null) {
            return $existing->status;
        }

        $this->executeReserved($job);
        return $job->status;
    }

    private function executeReserved(QueueEnvelope $job): string
    {
        $definition=$this->registry->job($job->type);
        $started=microtime(true);

        try {
            ($definition->handler)(
                $job->payload,
                new QueueJobContext($job->id,$job->attempts,$started,$job->correlationId),
            );

            if ((microtime(true)-$started)>$definition->timeoutSeconds) {
                throw new \RuntimeException('Queue job exceeded its soft timeout.');
            }

            $job->status=QueueJobStatus::Completed;
            $job->lastError=null;
            $job->updatedAt=microtime(true);
            $this->repository->save($job);
            $this->recordCost($job,$started,'completed');
            return 'completed';
        } catch (\Throwable $error) {
            $job->lastError=$this->safeError($error);
            $job->updatedAt=microtime(true);

            if ($job->attempts >= $definition->maxAttempts) {
                $job->status=QueueJobStatus::Dead;
                $this->repository->save($job);
                $this->recordCost($job,$started,'dead');
                return 'dead';
            }

            $job->status=QueueJobStatus::Failed;
            $job->availableAt=microtime(true)+$this->backoff($definition,$job->attempts);
            $this->repository->save($job);
            $this->recordCost($job,$started,'retried');
            return 'retried';
        }
    }

    private function recordCost(QueueEnvelope $job,float $started,string $outcome): void
    {
        if ($this->performance===null) return;
        $this->performance->gauge(
            'queue.job',
            PerformanceMetric::QueueJobMs,
            max(0.0,(microtime(true)-$started)*1000),
            ['job_type'=>$job->type,'outcome'=>$outcome,'attempt'=>$job->attempts],
        );
    }

    private function backoff(QueueJobDefinition $definition,int $attempt): int
    {
        $multiplier=2 ** max(0,min(10,$attempt-1));
        return min(86400,$definition->baseBackoffSeconds*$multiplier);
    }

    private function safeError(\Throwable $error): string
    {
        $message=trim($error->getMessage());
        $message=preg_replace(
            '#(?:[A-Za-z]:[\\\\/]|/)(?:[^\\s<>"\']+[\\\\/])+[^\\s<>"\']*#',
            '[path-redacted]',
            $message
        ) ?? $message;
        return substr($error::class . ': ' . $message,0,2000);
    }
}
