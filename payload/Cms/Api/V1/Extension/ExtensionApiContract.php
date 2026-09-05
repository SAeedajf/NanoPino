<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Extension;

final class ExtensionApiContract
{
    public const VERSION = 'v1';
    public const BASE = '/api/v1/cms/extensions';

    /** @return list<array{method:string,path:string,capability:string}> */
    public static function routes(): array
    {
        return [
            ['method' => 'GET', 'path' => self::BASE, 'capability' => 'extensions.read'],
            ['method' => 'POST', 'path' => self::BASE . '/inspect', 'capability' => 'extensions.install'],
            ['method' => 'POST', 'path' => self::BASE . '/review-ticket', 'capability' => 'extensions.install'],
            ['method' => 'POST', 'path' => self::BASE . '/install', 'capability' => 'extensions.install'],
            ['method' => 'POST', 'path' => self::BASE . '/{id}/activate', 'capability' => 'extensions.activate'],
            ['method' => 'POST', 'path' => self::BASE . '/{id}/deactivate', 'capability' => 'extensions.deactivate'],
            ['method' => 'POST', 'path' => self::BASE . '/{id}/update', 'capability' => 'extensions.update'],
            ['method' => 'POST', 'path' => self::BASE . '/{id}/rollback', 'capability' => 'extensions.repair'],
            ['method' => 'POST', 'path' => self::BASE . '/{id}/repair', 'capability' => 'extensions.repair'],
            ['method' => 'DELETE', 'path' => self::BASE . '/{id}', 'capability' => 'extensions.uninstall'],
        ];
    }
}
