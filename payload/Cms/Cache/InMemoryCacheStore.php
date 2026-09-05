<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

final class InMemoryCacheStore implements CacheStoreInterface
{
    /** @var array<string,array{value:mixed,expires:?float}> */
    private array $items=[];

    public function get(string $key,mixed $default=null): mixed
    {
        if (!isset($this->items[$key])) return $default;
        $item=$this->items[$key];
        if ($item['expires']!==null && $item['expires']<=microtime(true)) {
            unset($this->items[$key]);
            return $default;
        }
        return $item['value'];
    }

    public function set(string $key,mixed $value,?int $ttlSeconds=null): bool
    {
        $this->items[$key]=[
            'value'=>$value,
            'expires'=>$ttlSeconds!==null ? microtime(true)+max(1,$ttlSeconds) : null,
        ];
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        $sentinel=new \stdClass();
        return $this->get($key,$sentinel)!==$sentinel;
    }

    public function health(): array
    {
        return ['status'=>'ok','message'=>'In-memory cache is available.','details'=>['items'=>count($this->items)]];
    }
}
