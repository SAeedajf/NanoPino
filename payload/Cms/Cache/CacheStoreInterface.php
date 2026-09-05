<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

interface CacheStoreInterface
{
    public function get(string $key,mixed $default=null): mixed;
    public function set(string $key,mixed $value,?int $ttlSeconds=null): bool;
    public function delete(string $key): bool;
    public function has(string $key): bool;

    /** @return array{status:string,message:string,details?:array<string,mixed>} */
    public function health(): array;
}
