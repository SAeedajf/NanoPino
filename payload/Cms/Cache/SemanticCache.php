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
        $value=$this->store->get($this->physicalKey($layer,$key,$tags),$sentinel);
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
        $stored=$this->store->set($this->physicalKey($layer,$key,$tags),$value,$ttlSeconds);
        if ($stored) $this->effectiveness?->write();
        return $stored;
    }

    /** @param list<string> $tags */
    public function delete(CacheLayer $layer,string $key,array $tags=[]): bool
    {
        return $this->store->delete($this->physicalKey($layer,$key,$tags));
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
