<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Developer;

final class DeveloperApiContract
{
    public const BASE = '/api/v1/cms/developer';

    /** @return list<array{method:string,path:string,capability:string}> */
    public static function routes(): array
    {
        return [
            [
                'method' => 'POST',
                'path' => self::BASE . '/starter',
                'capability' => 'system.developer.generate',
            ],
        ];
    }
}
