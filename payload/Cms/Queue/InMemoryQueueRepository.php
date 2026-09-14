<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

final class InMemoryQueueRepository implements QueueRepositoryInterface
{
    /** @var array<string,QueueEnvelope> */
    private array $jobs=[];

    public function push(QueueEnvelope $job): QueueEnvelope
    {
        if (isset($this->jobs[$job->id])) throw new \RuntimeException('Queue job ID already exists.');
        if ($job->dedupKey!==null && ($existing=$this->findActiveByDedupKey($job->dedupKey))!==null) return $existing;
        $this->jobs[$job->id]=$job;
        return $job;
    }

    public function reserve(?float $now=null): ?QueueEnvelope
    {
        $now??=microtime(true);
        $candidates=array_values(array_filter(
            $this->jobs,
            static fn(QueueEnvelope $job):bool =>
                in_array($job->status,[QueueJobStatus::Pending,QueueJobStatus::Failed],true)
                && $job->availableAt<=$now
        ));
        usort($candidates,static fn($a,$b):int=>$a->availableAt<=>$b->availableAt ?: $a->createdAt<=>$b->createdAt);
        $job=$candidates[0]??null;
        if ($job===null) return null;
        $job->status=QueueJobStatus::Processing;
        $job->attempts++;
        $job->updatedAt=$now;
        return $job;
    }

    public function claim(string $id,?float $now=null): ?QueueEnvelope
    {
        $now??=microtime(true);
        $job=$this->jobs[$id]??null;
        if (
            $job===null
            || !in_array($job->status,[QueueJobStatus::Pending,QueueJobStatus::Failed],true)
            || $job->availableAt>$now
        ) return null;

        $job->status=QueueJobStatus::Processing;
        $job->attempts++;
        $job->updatedAt=$now;
        return $job;
    }

    public function save(QueueEnvelope $job): void { $this->jobs[$job->id]=$job; }
    public function find(string $id): ?QueueEnvelope { return $this->jobs[$id]??null; }

    public function findActiveByDedupKey(string $dedupKey): ?QueueEnvelope
    {
        foreach ($this->jobs as $job) {
            if ($job->dedupKey===$dedupKey && !in_array($job->status,[QueueJobStatus::Completed,QueueJobStatus::Dead],true)) return $job;
        }
        return null;
    }

    public function stats(): array
    {
        $stats=array_fill_keys(array_map(fn($c)=>$c->value,QueueJobStatus::cases()),0);
        foreach ($this->jobs as $job) $stats[$job->status->value]++;
        return $stats;
    }

    public function health(): array
    {
        return [
            'status'=>'ok',
            'message'=>'In-memory queue repository is available.',
            'details'=>['jobs'=>count($this->jobs)],
        ];
    }

    /** @return int number of processing jobs returned to the retryable state */
    public function recoverStaleProcessing(int $leaseSeconds=3600,?float $now=null): int
    {
        $leaseSeconds=max(60,min(86400,$leaseSeconds));
        $now??=microtime(true);
        $cutoff=$now-$leaseSeconds;
        $recovered=0;
        foreach ($this->jobs as $job) {
            if ($job->status!==QueueJobStatus::Processing || $job->updatedAt>$cutoff) continue;
            $job->status=QueueJobStatus::Failed;
            $job->availableAt=$now;
            $job->updatedAt=$now;
            $job->lastError='Recovered stale processing lease after worker interruption.';
            ++$recovered;
        }
        return $recovered;
    }

    public function recent(int $limit=100): array
    {
        $jobs=array_values($this->jobs);
        usort($jobs,static fn($a,$b):int=>$b->updatedAt<=>$a->updatedAt);
        return array_slice($jobs,0,max(1,min(500,$limit)));
    }
}
