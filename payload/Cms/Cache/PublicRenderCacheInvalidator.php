<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

/** Invalidates public render output after Builder, Theme or design changes. */
final readonly class PublicRenderCacheInvalidator
{
    public function __construct(private SemanticCache $cache) {}

    public function invalidateSite(int $siteId, ?string $themeReference = null): void
    {
        if ($siteId < 1) return;

        try {
            foreach ([CacheLayer::Page, CacheLayer::BuilderRender, CacheLayer::Api] as $layer) {
                $this->cache->invalidateLayer($layer);
            }

            $this->cache->invalidateTag('content:site:' . $siteId);
            $this->cache->invalidateTag('theme:site:' . $siteId);
            if ($themeReference !== null && $themeReference !== '') {
                $this->cache->invalidateTag('theme:' . $themeReference);
            }
        } catch (\Throwable) {
            // Rendering cache is secondary to a successful durable mutation.
        }
    }
}
