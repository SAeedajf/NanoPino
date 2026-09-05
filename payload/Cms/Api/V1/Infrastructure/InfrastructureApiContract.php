<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Infrastructure;

final class InfrastructureApiContract
{
    public const BASE='/api/v1/cms/system/infrastructure';

    /** @return list<array{method:string,path:string,capability:string}> */
    public static function routes(): array
    {
        return [
            ['method'=>'GET','path'=>self::BASE,'capability'=>'system.health.view'],
            ['method'=>'POST','path'=>self::BASE . '/cache/invalidate-tag','capability'=>'system.cache.manage'],
            ['method'=>'POST','path'=>self::BASE . '/cache/invalidate-layer','capability'=>'system.cache.manage'],
            ['method'=>'GET','path'=>self::BASE . '/queue','capability'=>'system.health.view'],
            ['method'=>'POST','path'=>self::BASE . '/queue/{id}/retry','capability'=>'system.queue.manage'],
        ];
    }
}
