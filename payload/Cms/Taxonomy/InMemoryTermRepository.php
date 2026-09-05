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
