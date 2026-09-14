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

    /** @param array<string,mixed> $changes */
    public function updateTerm(
        int $termId,
        array $changes,
        ?string $expectedTaxonomy = null,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): TermRecord {
        $current = $this->repository->find($termId);
        if ($current === null) {
            throw new ContentValidationException('Term not found.');
        }
        if ($expectedTaxonomy !== null && $current->taxonomy !== $expectedTaxonomy) {
            throw new ContentValidationException('Term does not belong to this taxonomy.');
        }

        $taxonomy = $this->taxonomies->definition($current->taxonomy);
        if ($taxonomy === null) {
            throw new ContentValidationException('Taxonomy not registered: ' . $current->taxonomy);
        }

        $this->authorization->authorize(new AuthorizationRequest(
            $taxonomy->permissions['manage'],
            $actorId,
            ScopeType::Site,
            $current->siteId,
            'taxonomy',
            $current->taxonomy,
        ));

        $name = array_key_exists('name', $changes) ? trim((string)$changes['name']) : $current->name;
        if ($name === '') {
            throw new ContentValidationException('Term name is required.');
        }
        if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 255) {
            throw new ContentValidationException('Term name exceeds 255 characters.');
        }

        $description = array_key_exists('description', $changes)
            ? trim((string)$changes['description'])
            : $current->description;
        if ((function_exists('mb_strlen') ? mb_strlen($description) : strlen($description)) > 5000) {
            throw new ContentValidationException('Term description exceeds 5000 characters.');
        }

        $locale = array_key_exists('locale', $changes) ? trim((string)$changes['locale']) : $current->locale;
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            throw new ContentValidationException('Invalid term locale.');
        }

        $parentId = array_key_exists('parent_id', $changes)
            ? ($changes['parent_id'] === null || $changes['parent_id'] === '' ? null : (int)$changes['parent_id'])
            : $current->parentId;
        if ($parentId !== null) {
            if (!$taxonomy->hierarchical || $parentId === $termId) {
                throw new ContentValidationException('Invalid term parent.');
            }
            $parent = $this->repository->find($parentId);
            if (
                $parent === null
                || $parent->siteId !== $current->siteId
                || $parent->taxonomy !== $current->taxonomy
                || $parent->locale !== $locale
            ) {
                throw new ContentValidationException('Invalid term parent.');
            }
            $this->assertNoParentCycle($termId, $parentId, $current->taxonomy);
        }

        $metadata = array_key_exists('metadata', $changes) ? $changes['metadata'] : $current->metadata;
        if (!is_array($metadata)) {
            throw new ContentValidationException('Term metadata must be an object.');
        }
        $metadataJson = json_encode($metadata);
        if (!is_string($metadataJson) || strlen($metadataJson) > 262144) {
            throw new ContentValidationException('Term metadata exceeds allowed size or is invalid.');
        }

        $candidateSlug = array_key_exists('slug', $changes) && trim((string)$changes['slug']) !== ''
            ? (string)$changes['slug']
            : $name;
        $slug = $this->slugger->uniqueTerm(
            $this->repository,
            $current->siteId,
            $current->taxonomy,
            $locale,
            $candidateSlug,
            $termId,
        );

        $term = $this->repository->update($termId, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => $parentId,
            'locale' => $locale,
            'metadata' => $metadata,
        ]);
        if ($term === null) {
            throw new ContentValidationException('Term not found.');
        }

        $this->audit->log(
            'taxonomy.term.update',
            $taxonomy->owner(),
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $current->siteId,
            'term',
            $term->id,
            $correlationId,
            ['taxonomy' => $current->taxonomy, 'locale' => $term->locale],
        );

        return $term;
    }

    public function deleteTerm(
        int $termId,
        ?string $expectedTaxonomy = null,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): void {
        $term = $this->repository->find($termId);
        if ($term === null) {
            throw new ContentValidationException('Term not found.');
        }
        if ($expectedTaxonomy !== null && $term->taxonomy !== $expectedTaxonomy) {
            throw new ContentValidationException('Term does not belong to this taxonomy.');
        }

        $taxonomy = $this->taxonomies->definition($term->taxonomy);
        if ($taxonomy === null) {
            throw new ContentValidationException('Taxonomy not registered: ' . $term->taxonomy);
        }

        $this->authorization->authorize(new AuthorizationRequest(
            $taxonomy->permissions['manage'],
            $actorId,
            ScopeType::Site,
            $term->siteId,
            'taxonomy',
            $term->taxonomy,
        ));

        if ($this->repository->hasChildren($termId)) {
            throw new ContentValidationException('Term has child terms and cannot be deleted.');
        }

        if (!$this->repository->delete($termId)) {
            throw new ContentValidationException('Term is assigned to content or no longer exists.');
        }

        $this->audit->log(
            'taxonomy.term.delete',
            $taxonomy->owner(),
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $term->siteId,
            'term',
            $term->id,
            $correlationId,
            ['taxonomy' => $term->taxonomy, 'locale' => $term->locale],
        );
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

    private function assertNoParentCycle(int $termId, int $parentId, string $taxonomy): void
    {
        $visited = [];
        $cursor = $parentId;
        while ($cursor > 0) {
            if (isset($visited[$cursor])) {
                throw new ContentValidationException('Term hierarchy contains a cycle.');
            }
            if ($cursor === $termId) {
                throw new ContentValidationException('Term cannot be moved below its own descendant.');
            }
            $visited[$cursor] = true;
            $parent = $this->repository->find($cursor);
            if ($parent === null || $parent->taxonomy !== $taxonomy || $parent->parentId === null) {
                return;
            }
            $cursor = $parent->parentId;
        }
    }
}
