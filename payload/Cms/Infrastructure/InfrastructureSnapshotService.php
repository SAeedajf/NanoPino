<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Infrastructure;

use App\com_pinoox_cms\Cms\Cache\CacheStoreInterface;
use App\com_pinoox_cms\Cms\Driver\DriverDefinition;
use App\com_pinoox_cms\Cms\Driver\DriverRegistry;
use App\com_pinoox_cms\Cms\Queue\QueueRepositoryInterface;
use App\com_pinoox_cms\Cms\Search\SearchDriverInterface;
use App\com_pinoox_cms\Cms\Storage\StorageDriverInterface;

final readonly class InfrastructureSnapshotService
{
    public function __construct(
        private DriverRegistry $drivers,
        private ?SearchDriverInterface $search=null,
        private ?CacheStoreInterface $cache=null,
        private ?QueueRepositoryInterface $queue=null,
        private ?StorageDriverInterface $storage=null,
    ) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return [
            'drivers'=>array_map(
                static fn(DriverDefinition $definition):array=>[
                    'id'=>$definition->identifier(),
                    'owner'=>$definition->owner(),
                    'kind'=>$definition->kind->value,
                    'label'=>$definition->label,
                    'metadata'=>$definition->metadata,
                ],
                array_values(array_filter(
                    $this->drivers->all(),
                    static fn($definition):bool=>$definition instanceof DriverDefinition
                )),
            ),
            'search'=>$this->search?->health() ?? [
                'status'=>'unbound',
                'message'=>'Active Search driver is not bound in Admin bootstrap.',
            ],
            'cache'=>$this->cache?->health() ?? [
                'status'=>'unbound',
                'message'=>'Active Cache adapter is not bound in Admin bootstrap.',
            ],
            'queue'=>$this->queue===null ? [
                'status'=>'unbound',
                'stats'=>[],
            ] : [
                ...$this->queue->health(),
                'stats'=>$this->queue->stats(),
            ],
            'storage'=>$this->storage?->health() ?? [
                'status'=>'unbound',
                'message'=>'Active Storage adapter is not bound in Admin bootstrap.',
            ],
            'scheduler'=>[
                'status'=>'native',
                'provider'=>'Pinoox ScheduleRegistry',
                'queue_role'=>'trigger only',
            ],
        ];
    }
}
