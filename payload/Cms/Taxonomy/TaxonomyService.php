<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Content\ContentValidationException;
use App\com_pinoox_cms\Cms\Content\Slugger;

final class TaxonomyService
{
    public function __construct(
        private readonly TaxonomyRegistry $taxonomies,
        private readonly TermRepositoryInterface $repository,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
        private readonly Slugger $slugger = new Slugger(),
    ) {}

    /** @param array<string,mixed> $metadata */
    public function createTerm(
        string $taxonomyKey,
        int $siteId,
        string $name,
        ?string $slug = null,
        string $description = '',
        ?int $parentId = null,
        string $locale = 'fa',
        array $metadata = [],
        ?int $actorId = null,
        ?string $correlationId = null,
    ): TermRecord {
        $taxonomy = $this->taxonomies->definition($taxonomyKey);
        if ($taxonomy === null) {
            throw new ContentValidationException('Taxonomy not registered: ' . $taxonomyKey);
        }

        $this->authorization->authorize(new AuthorizationRequest(
            $taxonomy->permissions['manage'],
            $actorId,
            ScopeType::Site,
            $siteId,
            'taxonomy',
            $taxonomyKey,
        ));

        $name = trim($name);
        if ($name === '') {
            throw new ContentValidationException('Term name is required.');
        }
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($nameLength > 255) {
            throw new ContentValidationException('Term name exceeds 255 characters.');
        }

        if ((function_exists('mb_strlen') ? mb_strlen($description) : strlen($description)) > 5000) {
            throw new ContentValidationException('Term description exceeds 5000 characters.');
        }

        $metadataJson = json_encode($metadata);
        if (!is_string($metadataJson) || strlen($metadataJson) > 262144) {
            throw new ContentValidationException('Term metadata exceeds allowed size or is invalid.');
        }

        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            throw new ContentValidationException('Invalid term locale.');
        }

        if ($parentId !== null) {
            if (!$taxonomy->hierarchical) {
                throw new ContentValidationException('Taxonomy is not hierarchical.');
            }
            $parent = $this->repository->find($parentId);
            if (
                $parent === null
                || $parent->siteId !== $siteId
                || $parent->taxonomy !== $taxonomyKey
                || $parent->locale !== $locale
            ) {
                throw new ContentValidationException('Invalid term parent.');
            }
        }

        $finalSlug = $this->slugger->uniqueTerm(
            $this->repository,
            $siteId,
            $taxonomyKey,
            $locale,
            $slug !== null && trim($slug) !== '' ? $slug : $name,
        );

        $term = $this->repository->create(
            $siteId,
            $taxonomyKey,
            $name,
            $finalSlug,
            trim($description),
            $parentId,
            $locale,
            $metadata,
        );

        $this->audit->log(
            'taxonomy.term.create',
            $taxonomy->owner(),
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $siteId,
            'term',
            $term->id,
            $correlationId,
            ['taxonomy' => $taxonomyKey, 'locale' => $locale],
        );

        return $term;
    }

    /** @return array{items:list<TermRecord>,total:int,limit:int,offset:int} */
    public function searchTerms(
        string $taxonomyKey,
        int $siteId,
        string $locale = 'fa',
        ?string $search = null,
        int $limit = 50,
        int $offset = 0,
        ?int $actorId = null,
    ): array {
        $taxonomy = $this->taxonomies->definition($taxonomyKey);
        if ($taxonomy === null) {
            throw new ContentValidationException('Taxonomy not registered: ' . $taxonomyKey);
        }

        $this->authorization->authorize(new AuthorizationRequest(
            $taxonomy->permissions['read'],
            $actorId,
            ScopeType::Site,
            $siteId,
            'taxonomy',
            $taxonomyKey,
        ));

        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $search = trim((string)$search);

        return [
            'items' => $this->repository->searchForTaxonomy(
                $siteId,
                $taxonomyKey,
                $locale,
                $search !== '' ? $search : null,
                $limit,
                $offset,
            ),
            'total' => $this->repository->countForTaxonomy(
                $siteId,
                $taxonomyKey,
                $locale,
                $search !== '' ? $search : null,
            ),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /** @return list<TermRecord> */
    public function listTerms(
        string $taxonomyKey,
        int $siteId,
        string $locale = 'fa',
        ?int $actorId = null,
    ): array {
        $taxonomy = $this->taxonomies->definition($taxonomyKey);
        if ($taxonomy === null) {
            throw new ContentValidationException('Taxonomy not registered: ' . $taxonomyKey);
        }

        $this->authorization->authorize(new AuthorizationRequest(
            $taxonomy->permissions['read'],
            $actorId,
            ScopeType::Site,
            $siteId,
            'taxonomy',
            $taxonomyKey,
        ));

        return $this->repository->forTaxonomy($siteId, $taxonomyKey, $locale);
    }
}
