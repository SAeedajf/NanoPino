<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Cache\CacheLayer;
use App\com_pinoox_cms\Cms\Cache\CacheStoreInterface;
use App\com_pinoox_cms\Cms\Cache\CacheTagClockInterface;
use App\com_pinoox_cms\Cms\Cache\InMemoryCacheStore;
use App\com_pinoox_cms\Cms\Cache\SemanticCache;

final class R23FailingCacheStore implements CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        throw new RuntimeException('cache backend unavailable');
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): bool
    {
        throw new RuntimeException('cache backend unavailable');
    }

    public function delete(string $key): bool
    {
        throw new RuntimeException('cache backend unavailable');
    }

    public function has(string $key): bool
    {
        throw new RuntimeException('cache backend unavailable');
    }

    public function health(): array
    {
        return ['status' => 'error', 'message' => 'unavailable'];
    }
}

final class R23FailingCacheTagClock implements CacheTagClockInterface
{
    public function generation(string $tag): int
    {
        throw new RuntimeException('cache tag clock unavailable');
    }

    public function bump(string $tag): int
    {
        throw new RuntimeException('cache tag clock unavailable');
    }
}

return [
    'Semantic cache fails open when the native cache backend is unavailable' => static function (): void {
        $cache = new SemanticCache(new R23FailingCacheStore(), new \App\com_pinoox_cms\Cms\Cache\InMemoryCacheTagClock());

        np_assert_same('authoritative', $cache->get(CacheLayer::Api, 'design', [], 'authoritative'));
        np_assert_false($cache->set(CacheLayer::Api, 'design', ['value' => 1], 30));
        np_assert_false($cache->delete(CacheLayer::Api, 'design'));
    },

    'Semantic cache fails open when tag-clock metadata is unavailable' => static function (): void {
        $cache = new SemanticCache(new InMemoryCacheStore(), new R23FailingCacheTagClock());

        np_assert_same(null, $cache->get(CacheLayer::Page, 'home'));
        np_assert_false($cache->set(CacheLayer::Page, 'home', '<html>'));
        np_assert_false($cache->delete(CacheLayer::Page, 'home'));
    },

    'Semantic cache still rejects invalid caller keys after stability guard' => static function (): void {
        $cache = new SemanticCache(new InMemoryCacheStore(), new \App\com_pinoox_cms\Cms\Cache\InMemoryCacheTagClock());

        np_assert_throws(
            static fn () => $cache->get(CacheLayer::Api, ''),
            InvalidArgumentException::class,
            'Invalid cache key',
        );
    },
];
