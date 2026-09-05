<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

final readonly class QueueMonitor
{
    public function __construct(private QueueRepositoryInterface $repository) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $stats=$this->repository->stats();
        $recent=array_map(
            static fn(QueueEnvelope $job):array=>$job->toArray(true),
            $this->repository->recent(20),
        );

        $health=$this->repository->health();

        return [
            'stats'=>$stats,
            'recent'=>$recent,
            'repository'=>$health,
            'healthy'=>($stats['dead']??0)===0 && ($health['status']??'error')==='ok',
        ];
    }
}
