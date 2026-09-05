<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\Search;

final class SearchApiContract
{
    public const BASE='/api/v1/cms/search';

    /** @return list<array{method:string,path:string,capability:string}> */
    public static function routes(): array
    {
        return [
            ['method'=>'GET','path'=>self::BASE,'capability'=>'content.read'],
        ];
    }
}
