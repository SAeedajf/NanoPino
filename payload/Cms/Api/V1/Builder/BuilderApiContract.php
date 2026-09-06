<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Builder;

final class BuilderApiContract
{
    public const VERSION = 'v1';
    public const BASE = '/api/v1/cms/builder';

    /** @return list<array{method:string,path:string,capability:string}> */
    public static function routes(): array
    {
        return [
            ['method' => 'GET', 'path' => self::BASE . '/global-blocks', 'capability' => 'builder.read'],
            ['method' => 'POST', 'path' => self::BASE . '/global-blocks', 'capability' => 'builder.edit'],
            ['method' => 'GET', 'path' => self::BASE . '/global-blocks/{id}', 'capability' => 'builder.read'],
            ['method' => 'PUT', 'path' => self::BASE . '/global-blocks/{id}', 'capability' => 'builder.edit'],
            ['method' => 'GET', 'path' => self::BASE, 'capability' => 'builder.read'],
            ['method' => 'POST', 'path' => self::BASE . '/open', 'capability' => 'builder.edit'],
            ['method' => 'POST', 'path' => self::BASE, 'capability' => 'builder.edit'],
            ['method' => 'GET', 'path' => self::BASE . '/{id}', 'capability' => 'builder.read'],
            ['method' => 'PUT', 'path' => self::BASE . '/{id}', 'capability' => 'builder.edit'],
            ['method' => 'POST', 'path' => self::BASE . '/{id}/autosave', 'capability' => 'builder.edit'],
            ['method' => 'POST', 'path' => self::BASE . '/{id}/publish', 'capability' => 'builder.publish'],
            ['method' => 'GET', 'path' => self::BASE . '/{id}/revisions', 'capability' => 'builder.read'],
            ['method' => 'POST', 'path' => self::BASE . '/{id}/revisions/{revisionId}/restore', 'capability' => 'builder.edit'],
            ['method' => 'POST', 'path' => self::BASE . '/preview', 'capability' => 'builder.preview'],
        ];
    }
}
