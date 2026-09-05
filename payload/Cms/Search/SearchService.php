<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;

final readonly class SearchService
{
    public function __construct(
        private AuthorizationManager $authorization,
        private SearchManager $search,
    ) {}

    public function search(SearchQuery $query,?int $actorId=null): SearchResult
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'content.read',
            $actorId,
            ScopeType::Site,
            $query->siteId,
            'search',
            'site:' . $query->siteId,
        ));

        // Search indexes can contain documents owned by many authors. Until the
        // SearchDriver contract exposes an owner filter, fail closed for roles that
        // may only access their own content instead of leaking cross-owner hits.
        $this->authorization->authorize(new AuthorizationRequest(
            'content.manage_others',
            $actorId,
            ScopeType::Site,
            $query->siteId,
            'search',
            'site:' . $query->siteId,
        ));

        return $this->search->search($query);
    }
}
