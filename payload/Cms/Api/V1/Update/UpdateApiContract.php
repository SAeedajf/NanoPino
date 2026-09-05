<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Update;

final class UpdateApiContract
{
    public const BASE = '/api/v1/cms/updates';

    /** @return list<array{method:string,path:string,capability:string}> */
    public static function routes(): array
    {
        return [
            ['method'=>'GET','path'=>self::BASE . '/{id}/policy','capability'=>'extensions.read'],
            ['method'=>'PUT','path'=>self::BASE . '/{id}/policy','capability'=>'extensions.update'],
            ['method'=>'GET','path'=>self::BASE . '/{id}/history','capability'=>'extensions.read'],
            ['method'=>'GET','path'=>self::BASE . '/{id}/recovery-points','capability'=>'system.recovery'],
        ];
    }
}
