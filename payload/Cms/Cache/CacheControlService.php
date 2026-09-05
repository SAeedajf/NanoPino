<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;

final readonly class CacheControlService
{
    public function __construct(
        private AuthorizationManager $authorization,
        private SemanticCache $cache,
    ) {}

    public function invalidateTag(string $tag,?int $actorId=null): int
    {
        $this->authorization->authorize(new AuthorizationRequest('system.cache.manage',$actorId));
        return $this->cache->invalidateTag($tag);
    }

    public function invalidateLayer(CacheLayer $layer,?int $actorId=null): int
    {
        $this->authorization->authorize(new AuthorizationRequest('system.cache.manage',$actorId));
        return $this->cache->invalidateLayer($layer);
    }
}
