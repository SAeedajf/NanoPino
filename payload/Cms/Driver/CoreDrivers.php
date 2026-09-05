<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Driver;

use App\com_pinoox_cms\Cms\Cache\PinooxCacheStore;
use App\com_pinoox_cms\Cms\Queue\FileQueueRepository;
use App\com_pinoox_cms\Cms\Search\MeilisearchSearchDriver;
use App\com_pinoox_cms\Cms\Search\PinooxDatabaseSearchDriver;
use App\com_pinoox_cms\Cms\Search\TypesenseSearchDriver;
use App\com_pinoox_cms\Cms\Search\UnboundRemoteSearchTransport;
use App\com_pinoox_cms\Cms\Storage\PinooxStorageDriver;
use Pinoox\Support\SystemConfig;

final class CoreDrivers
{
    public const OWNER='cms.core';

    public static function register(DriverRegistry $registry): void
    {
        $registry->register(new DriverDefinition(
            'search.database',
            self::OWNER,
            DriverKind::Search,
            'Database Search',
            static fn()=>new PinooxDatabaseSearchDriver(),
            ['baseline'=>true,'remote'=>false],
        ));
        $registry->register(new DriverDefinition(
            'search.meilisearch',
            self::OWNER,
            DriverKind::Search,
            'Meilisearch',
            static fn()=>new MeilisearchSearchDriver(new UnboundRemoteSearchTransport()),
            ['baseline'=>false,'remote'=>true,'requires'=>'configured transport'],
        ));
        $registry->register(new DriverDefinition(
            'search.typesense',
            self::OWNER,
            DriverKind::Search,
            'Typesense',
            static fn()=>new TypesenseSearchDriver(new UnboundRemoteSearchTransport()),
            ['baseline'=>false,'remote'=>true,'requires'=>'configured transport'],
        ));
        $registry->register(new DriverDefinition(
            'cache.pinoox',
            self::OWNER,
            DriverKind::Cache,
            'Pinoox Cache',
            static fn()=>new PinooxCacheStore(),
            ['native'=>true,'stores'=>['file','redis']],
        ));
        $registry->register(new DriverDefinition(
            'queue.file',
            self::OWNER,
            DriverKind::Queue,
            'File Queue',
            static fn()=>new FileQueueRepository(
                SystemConfig::resolvePath('~storage/cms/queue')
            ),
            ['shared_hosting'=>true,'worker_required'=>false],
        ));
        $registry->register(new DriverDefinition(
            'storage.pinoox',
            self::OWNER,
            DriverKind::Storage,
            'Pinoox Storage',
            static fn()=>new PinooxStorageDriver(),
            ['native'=>true,'drivers'=>['local','ftp','sftp','s3','custom']],
        ));
    }
}
