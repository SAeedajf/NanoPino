<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

interface TermRepositoryInterface
{
    /** @param array<string,mixed> $metadata */
    public function create(
        int $siteId,
        string $taxonomy,
        string $name,
        string $slug,
        string $description,
        ?int $parentId,
        string $locale,
        array $metadata = [],
    ): TermRecord;

    public function find(int $id): ?TermRecord;

    public function findBySlug(int $siteId, string $taxonomy, string $locale, string $slug): ?TermRecord;

    /** @param array<string,mixed> $changes */
    public function update(int $id, array $changes): ?TermRecord;

    /**
     * Deletes a term only when it has no content assignments.
     * Implementations must return false for an unknown term.
     */
    public function delete(int $id): bool;

    /** Returns true when the term has child terms and cannot be safely removed. */
    public function hasChildren(int $id): bool;

    /** @param list<int> $ids @return array<int,TermRecord> keyed by id */
    public function findMany(array $ids): array;

    /** @return list<TermRecord> */
    public function forTaxonomy(int $siteId, string $taxonomy, string $locale = 'fa'): array;

    /** @return list<TermRecord> */
    public function searchForTaxonomy(
        int $siteId,
        string $taxonomy,
        string $locale = 'fa',
        ?string $search = null,
        int $limit = 50,
        int $offset = 0,
    ): array;

    public function countForTaxonomy(
        int $siteId,
        string $taxonomy,
        string $locale = 'fa',
        ?string $search = null,
    ): int;

    public function slugExists(
        int $siteId,
        string $taxonomy,
        string $locale,
        string $slug,
        ?int $excludeId = null,
    ): bool;

    /** @param list<int> $termIds */
    public function assignToContent(int $contentId, string $taxonomy, array $termIds): void;

    /** @return list<int> */
    public function assignedTermIds(int $contentId, string $taxonomy): array;
}
