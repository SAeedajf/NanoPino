<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

use App\com_pinoox_cms\Cms\Performance\Cache\CacheEffectivenessTracker;

final readonly class SemanticCache
{
    public function __construct(
        private CacheStoreInterface $store,
        private CacheTagClockInterface $clock,
        private string $namespace='cms',
        private ?CacheEffectivenessTracker $effectiveness=null,
    ) {
        if (preg_match('/^[a-zA-Z0-9._-]{1,64}$/',$namespace)!==1) {
            throw new \InvalidArgumentException('Invalid cache namespace.');
        }
    }

    /** @param list<string> $tags */
    public function get(CacheLayer $layer,string $key,array $tags=[],mixed $default=null): mixed
    {
        $sentinel=new \stdClass();
        try {
            $physicalKey=$this->physicalKey($layer,$key,$tags);
        } catch (\InvalidArgumentException $error) {
            throw $error;
        } catch (\Throwable) {
            // Cache metadata is an optimization. A broken tag clock must not
            // take a read path down with it.
            $this->effectiveness?->miss();
            return $default;
        }
        try {
            $value=$this->store->get($physicalKey,$sentinel);
        } catch (\Throwable) {
            // Fail open when the native cache backend is temporarily down;
            // callers can continue from the authoritative source.
            $this->effectiveness?->miss();
            return $default;
        }
        if ($value===$sentinel) {
            $this->effectiveness?->miss();
            return $default;
        }
        $this->effectiveness?->hit();
        return $value;
    }

    /** @param list<string> $tags */
    public function set(CacheLayer $layer,string $key,mixed $value,?int $ttlSeconds=null,array $tags=[]): bool
    {
        try {
            $physicalKey=$this->physicalKey($layer,$key,$tags);
        } catch (\InvalidArgumentException $error) {
            throw $error;
        } catch (\Throwable) {
            return false;
        }
        try {
            $stored=$this->store->set($physicalKey,$value,$ttlSeconds);
        } catch (\Throwable) {
            return false;
        }
        if ($stored) $this->effectiveness?->write();
        return $stored;
    }

    /** @param list<string> $tags */
    public function delete(CacheLayer $layer,string $key,array $tags=[]): bool
    {
        try {
            $physicalKey=$this->physicalKey($layer,$key,$tags);
        } catch (\InvalidArgumentException $error) {
            throw $error;
        } catch (\Throwable) {
            return false;
        }
        try {
            return $this->store->delete($physicalKey);
        } catch (\Throwable) {
            return false;
        }
    }

    public function invalidateTag(string $tag): int
    {
        return $this->clock->bump('tag:' . $this->validateTag($tag));
    }

    public function invalidateLayer(CacheLayer $layer): int
    {
        return $this->clock->bump('layer:' . $layer->value);
    }

    /** @param list<string> $tags */
    private function physicalKey(CacheLayer $layer,string $key,array $tags): string
    {
        if ($key==='' || strlen($key)>500) throw new \InvalidArgumentException('Invalid cache key.');

        $parts=[
            $this->namespace,
            $layer->value,
            'l' . $this->clock->generation('layer:' . $layer->value),
        ];

        $tags=array_values(array_unique($tags));
        sort($tags);
        foreach ($tags as $tag) {
            $tag=$this->validateTag($tag);
            $parts[]='t' . hash('sha256',$tag . ':' . $this->clock->generation('tag:' . $tag));
        }

        $parts[]='k' . hash('sha256',$key);
        return implode(':',$parts);
    }

    private function validateTag(string $tag): string
    {
        if (preg_match('/^[a-zA-Z0-9._:-]{1,190}$/',$tag)!==1) {
            throw new \InvalidArgumentException('Invalid cache tag.');
        }
        return $tag;
    }
}
