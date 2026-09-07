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
