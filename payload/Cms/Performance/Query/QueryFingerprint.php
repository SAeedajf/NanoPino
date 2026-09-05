<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

final class QueryFingerprint
{
    public static function normalize(string $sql): string
    {
        $sql=preg_replace("/'(?:''|[^'])*'/","?",$sql) ?? $sql;
        $sql=preg_replace('/"(?:\\"|[^"])*"/','?',$sql) ?? $sql;
        $sql=preg_replace('/\b\d+(?:\.\d+)?\b/','?',$sql) ?? $sql;
        $sql=preg_replace('/\s+/',' ',trim(strtolower($sql))) ?? $sql;
        return strlen($sql)>4096 ? substr($sql,0,4096).'…' : $sql;
    }

    public static function fromSql(string $sql): string
    {
        return hash('sha256',self::normalize($sql));
    }
}
