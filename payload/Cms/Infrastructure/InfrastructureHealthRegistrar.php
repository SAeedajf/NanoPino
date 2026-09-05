<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Infrastructure;

use App\com_pinoox_cms\Cms\Cache\CacheStoreInterface;
use App\com_pinoox_cms\Cms\Health\HealthCheckDefinition;
use App\com_pinoox_cms\Cms\Health\HealthCheckRegistry;
use App\com_pinoox_cms\Cms\Queue\QueueRepositoryInterface;
use App\com_pinoox_cms\Cms\Search\SearchDriverInterface;
use App\com_pinoox_cms\Cms\Storage\StorageDriverInterface;

final readonly class InfrastructureHealthRegistrar
{
    public function __construct(
        private SearchDriverInterface $search,
        private CacheStoreInterface $cache,
        private QueueRepositoryInterface $queue,
        private StorageDriverInterface $storage,
    ) {}

    public function register(HealthCheckRegistry $registry,string $owner='cms.core'): void
    {
        $search=$this->search;
        $cache=$this->cache;
        $queue=$this->queue;
        $storage=$this->storage;

        $registry->register(new HealthCheckDefinition(
            'infrastructure.search',
            $owner,
            static fn():array=>$search->health(),
        ));
        $registry->register(new HealthCheckDefinition(
            'infrastructure.cache',
            $owner,
            static fn():array=>$cache->health(),
        ));
        $registry->register(new HealthCheckDefinition(
            'infrastructure.queue',
            $owner,
            static fn():array=>$queue->health(),
        ));
        $registry->register(new HealthCheckDefinition(
            'infrastructure.storage',
            $owner,
            static fn():array=>$storage->health(),
        ));
    }
}
