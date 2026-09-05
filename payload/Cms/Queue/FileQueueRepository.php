<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

use RuntimeException;

final readonly class FileQueueRepository implements QueueRepositoryInterface
{
    public function __construct(private string $directory) {}

    public function push(QueueEnvelope $job): QueueEnvelope
    {
        return $this->withLock(function () use ($job): QueueEnvelope {
            if ($job->dedupKey !== null) {
                $existing=$this->findActiveByDedupKeyUnlocked($job->dedupKey);
                if ($existing!==null) return $existing;
            }
            $path=$this->jobPath($job->id);
            if (is_file($path)) throw new RuntimeException('Queue job ID already exists.');
            $this->writeJob($job);
            return $job;
        });
    }

    public function reserve(?float $now=null): ?QueueEnvelope
    {
        $now??=microtime(true);
        return $this->withLock(function () use ($now): ?QueueEnvelope {
            $jobs=$this->loadAllUnlocked();
            $candidates=array_values(array_filter(
                $jobs,
                static fn(QueueEnvelope $job):bool =>
                    in_array($job->status,[QueueJobStatus::Pending,QueueJobStatus::Failed],true)
                    && $job->availableAt<=$now
            ));

            usort($candidates,static fn($a,$b):int =>
                $a->availableAt<=>$b->availableAt ?: $a->createdAt<=>$b->createdAt
            );

            $job=$candidates[0]??null;
            if ($job===null) return null;

            $job->status=QueueJobStatus::Processing;
            $job->attempts++;
            $job->updatedAt=$now;
            $this->writeJob($job);
            return $job;
        });
    }

    public function claim(string $id,?float $now=null): ?QueueEnvelope
    {
        $now??=microtime(true);
        return $this->withLock(function () use ($id,$now): ?QueueEnvelope {
            $path=$this->jobPath($id);
            if (!is_file($path)) return null;
            $job=$this->readJob($path);
            if (
                $job===null
                || !in_array($job->status,[QueueJobStatus::Pending,QueueJobStatus::Failed],true)
                || $job->availableAt>$now
            ) return null;

            $job->status=QueueJobStatus::Processing;
            $job->attempts++;
            $job->updatedAt=$now;
            $this->writeJob($job);
            return $job;
        });
    }

    public function save(QueueEnvelope $job): void
    {
        $this->withLock(function () use ($job): void {
            $this->writeJob($job);
        });
    }

    public function find(string $id): ?QueueEnvelope
    {
        $this->ensureDirectory();
        $path=$this->jobPath($id);
        if (!is_file($path)) return null;
        return $this->readJob($path);
    }

    public function findActiveByDedupKey(string $dedupKey): ?QueueEnvelope
    {
        return $this->withLock(fn():?QueueEnvelope=>$this->findActiveByDedupKeyUnlocked($dedupKey));
    }

    public function stats(): array
    {
        $stats=array_fill_keys(array_map(fn($c)=>$c->value,QueueJobStatus::cases()),0);
        foreach ($this->loadAll() as $job) $stats[$job->status->value]++;
        return $stats;
    }

    public function health(): array
    {
        try {
            $this->ensureDirectory();
            $corrupt=0;
            $count=0;
            foreach (glob(rtrim($this->directory,'/\\') . '/job-*.json') ?: [] as $file) {
                ++$count;
                try {
                    $this->readJob($file);
                } catch (\Throwable) {
                    ++$corrupt;
                }
            }

            return [
                'status'=>$corrupt===0 ? 'ok' : 'error',
                'message'=>$corrupt===0
                    ? 'File queue repository is available.'
                    : 'File queue contains corrupt job records.',
                'details'=>['jobs'=>$count,'corrupt'=>$corrupt],
            ];
        } catch (\Throwable $error) {
            return [
                'status'=>'error',
                'message'=>'File queue repository is unavailable.',
                'details'=>['error_class'=>$error::class],
            ];
        }
    }

    public function recent(int $limit=100): array
    {
        $jobs=$this->loadAll();
        usort($jobs,static fn($a,$b):int=>$b->updatedAt<=>$a->updatedAt);
        return array_slice($jobs,0,max(1,min(500,$limit)));
    }

    /** @return list<QueueEnvelope> */
    private function loadAll(): array
    {
        return $this->withLock(fn():array=>$this->loadAllUnlocked());
    }

    /** @return list<QueueEnvelope> */
    private function loadAllUnlocked(): array
    {
        $this->ensureDirectory();
        $jobs=[];
        foreach (glob(rtrim($this->directory,'/\\') . '/job-*.json') ?: [] as $file) {
            try {
                $job=$this->readJob($file);
                if ($job!==null) $jobs[]=$job;
            } catch (\Throwable) {
                // Corrupt job file is ignored here and will surface through health diagnostics.
            }
        }
        return $jobs;
    }

    private function findActiveByDedupKeyUnlocked(string $dedupKey): ?QueueEnvelope
    {
        foreach ($this->loadAllUnlocked() as $job) {
            if (
                $job->dedupKey===$dedupKey
                && !in_array($job->status,[QueueJobStatus::Completed,QueueJobStatus::Dead],true)
            ) return $job;
        }
        return null;
    }

    private function readJob(string $file): ?QueueEnvelope
    {
        $raw=file_get_contents($file);
        if ($raw===false || trim($raw)==='') return null;
        $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        return is_array($data) ? QueueEnvelope::fromArray($data) : null;
    }

    private function writeJob(QueueEnvelope $job): void
    {
        $this->ensureDirectory();
        $file=$this->jobPath($job->id);
        $tmp=$file . '.tmp-' . bin2hex(random_bytes(4));
        $json=json_encode($job->toArray(),JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($tmp,$json,LOCK_EX)===false || !@rename($tmp,$file)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to persist queue job.');
        }
    }

    private function withLock(callable $callback): mixed
    {
        $this->ensureDirectory();
        $lockPath=rtrim($this->directory,'/\\') . '/queue.lock';
        $handle=fopen($lockPath,'c+');
        if ($handle===false) throw new RuntimeException('Unable to open queue lock.');
        try {
            if (!flock($handle,LOCK_EX)) throw new RuntimeException('Unable to acquire queue lock.');
            return $callback();
        } finally {
            @flock($handle,LOCK_UN);
            @fclose($handle);
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory,0700,true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create queue directory.');
        }
    }

    private function jobPath(string $id): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/',$id)!==1) {
            throw new RuntimeException('Invalid queue job id.');
        }
        return rtrim($this->directory,'/\\') . '/job-' . $id . '.json';
    }
}
