<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

final class InMemoryTermRepository implements TermRepositoryInterface
{
    /** @var array<int,TermRecord> */
    private array $terms = [];

    /** @var array<int,array<string,list<int>>> */
    private array $assignments = [];

    private int $nextId = 1;

    public function create(
        int $siteId,
        string $taxonomy,
        string $name,
        string $slug,
        string $description,
        ?int $parentId,
        string $locale,
        array $metadata = [],
    ): TermRecord {
        $id = $this->nextId++;
        $now = gmdate(DATE_ATOM);
        $term = new TermRecord(
            $id,
            $siteId,
            $taxonomy,
            $name,
            $slug,
            $description,
            $parentId,
            $locale,
            $metadata,
            $now,
            $now,
        );
        $this->terms[$id] = $term;
        return $term;
    }

    public function find(int $id): ?TermRecord
    {
        return $this->terms[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        $result = [];
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            if (isset($this->terms[$id])) {
                $result[$id] = $this->terms[$id];
            }
        }

        return $result;
    }

    public function forTaxonomy(int $siteId, string $taxonomy, string $locale = 'fa'): array
    {
        $terms = array_values(array_filter(
            $this->terms,
            static fn (TermRecord $term): bool =>
                $term->siteId === $siteId
                && $term->taxonomy === $taxonomy
                && $term->locale === $locale
        ));
        usort($terms, static fn (TermRecord $a, TermRecord $b): int =>
            [$a->name, $a->id] <=> [$b->name, $b->id]
        );
        return $terms;
    }

    public function searchForTaxonomy(
        int $siteId,
        string $taxonomy,
        string $locale = 'fa',
        ?string $search = null,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $needle = function_exists('mb_strtolower')
            ? mb_strtolower(trim((string)$search))
            : strtolower(trim((string)$search));
        $terms = array_values(array_filter(
            $this->forTaxonomy($siteId, $taxonomy, $locale),
            static function (TermRecord $term) use ($needle): bool {
                if ($needle === '') return true;
                $name = function_exists('mb_strtolower') ? mb_strtolower($term->name) : strtolower($term->name);
                $slug = function_exists('mb_strtolower') ? mb_strtolower($term->slug) : strtolower($term->slug);
                return str_contains($name, $needle) || str_contains($slug, $needle);
            },
        ));
        return array_slice($terms, max(0, $offset), max(1, min(100, $limit)));
    }

    public function countForTaxonomy(
        int $siteId,
        string $taxonomy,
        string $locale = 'fa',
        ?string $search = null,
    ): int {
        $needle = function_exists('mb_strtolower')
            ? mb_strtolower(trim((string)$search))
            : strtolower(trim((string)$search));
        return count(array_filter(
            $this->forTaxonomy($siteId, $taxonomy, $locale),
            static function (TermRecord $term) use ($needle): bool {
                if ($needle === '') return true;
                $name = function_exists('mb_strtolower') ? mb_strtolower($term->name) : strtolower($term->name);
                $slug = function_exists('mb_strtolower') ? mb_strtolower($term->slug) : strtolower($term->slug);
                return str_contains($name, $needle) || str_contains($slug, $needle);
            },
        ));
    }

    public function slugExists(
        int $siteId,
        string $taxonomy,
        string $locale,
        string $slug,
        ?int $excludeId = null,
    ): bool {
        foreach ($this->terms as $term) {
            if ($excludeId !== null && $term->id === $excludeId) continue;
            if (
                $term->siteId === $siteId
                && $term->taxonomy === $taxonomy
                && $term->locale === $locale
                && $term->slug === $slug
            ) {
                return true;
            }
        }
        return false;
    }

    public function assignToContent(int $contentId, string $taxonomy, array $termIds): void
    {
        $this->assignments[$contentId][$taxonomy] = array_values(array_unique(array_map('intval', $termIds)));
    }

    public function assignedTermIds(int $contentId, string $taxonomy): array
    {
        return $this->assignments[$contentId][$taxonomy] ?? [];
    }
}
