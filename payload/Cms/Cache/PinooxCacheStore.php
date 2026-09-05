<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

use Pinoox\Portal\Cache;

final class PinooxCacheStore implements CacheStoreInterface
{
    public function get(string $key,mixed $default=null): mixed { return Cache::get($key,$default); }
    public function set(string $key,mixed $value,?int $ttlSeconds=null): bool { return Cache::set($key,$value,$ttlSeconds); }
    public function delete(string $key): bool { return Cache::delete($key); }
    public function has(string $key): bool { return Cache::has($key); }

    public function health(): array
    {
        try {
            $key='cms-health-' . bin2hex(random_bytes(5));
            Cache::set($key,'ok',5);
            $ok=Cache::get($key)==='ok';
            Cache::delete($key);
            return [
                'status'=>$ok?'ok':'error',
                'message'=>$ok?'Pinoox Cache is reachable.':'Pinoox Cache read/write verification failed.',
            ];
        } catch (\Throwable $error) {
            return [
                'status'=>'error',
                'message'=>'Pinoox Cache is unavailable.',
                'details'=>['error_class'=>$error::class],
            ];
        }
    }
}
