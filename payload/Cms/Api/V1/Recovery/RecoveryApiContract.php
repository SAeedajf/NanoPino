<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Recovery;

final class RecoveryApiContract
{
    public const BASE = '/api/v1/cms/recovery';

    /** @return list<array{method:string,path:string,capability:string}> */
    public static function routes(): array
    {
        return [
            ['method'=>'POST','path'=>self::BASE . '/points/{id}/restore','capability'=>'system.recovery'],
            ['method'=>'POST','path'=>self::BASE . '/safe-mode/disable','capability'=>'system.recovery'],
        ];
    }
}
