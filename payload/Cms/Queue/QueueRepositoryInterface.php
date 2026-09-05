<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

interface QueueRepositoryInterface
{
    public function push(QueueEnvelope $job): QueueEnvelope;
    public function reserve(?float $now=null): ?QueueEnvelope;
    public function claim(string $id,?float $now=null): ?QueueEnvelope;
    public function save(QueueEnvelope $job): void;
    public function find(string $id): ?QueueEnvelope;
    public function findActiveByDedupKey(string $dedupKey): ?QueueEnvelope;

    /** @return array<string,int> */
    public function stats(): array;

    /** @return array{status:string,message:string,details?:array<string,mixed>} */
    public function health(): array;

    /** @return list<QueueEnvelope> */
    public function recent(int $limit=100): array;
}
