<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

use App\com_pinoox_cms\Cms\Content\ContentRecord;

/**
 * Keeps content-backed semantic cache entries coherent after a mutation.
 * Cache is an optimization, never part of the write transaction.
 */
final readonly class ContentCacheInvalidator
{
    public function __construct(private SemanticCache $cache) {}

    public function invalidate(ContentRecord $record, ?ContentRecord $previous = null): void
    {
        try {
            foreach (CacheLayer::cases() as $layer) {
                $this->cache->invalidateLayer($layer);
            }

            $records = [$record];
            if ($previous !== null) $records[] = $previous;

            $tags = ['content'];
            foreach ($records as $item) {
                $tags[] = 'content:site:' . $item->siteId;
                $tags[] = 'content:id:' . $item->id;
                $tags[] = 'content:type:' . $item->type;
                $tags[] = 'content:locale:' . $item->locale;
            }

            foreach (array_values(array_unique($tags)) as $tag) {
                $this->cache->invalidateTag($tag);
            }
        } catch (\Throwable) {
            // Cache failure must not roll back a durable content mutation.
        }
    }
}
