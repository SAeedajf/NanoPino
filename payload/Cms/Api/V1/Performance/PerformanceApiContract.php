<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Performance;

final class PerformanceApiContract
{
    public const BASE='/api/v1/cms/system/performance';

    /** @return list<array{method:string,path:string,capability:string,rate_limit:string}> */
    public static function routes(): array
    {
        return [
            [
                'method'=>'GET',
                'path'=>self::BASE,
                'capability'=>'system.performance.view',
                'rate_limit'=>'cms.api.read',
            ],
        ];
    }
}
