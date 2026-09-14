<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use DateTimeImmutable;

final class InMemoryContentRepository implements ContentRepositoryInterface
{
    /** @var array<int,ContentRecord> */
    private array $records = [];
    private int $nextId = 1;

    public function create(ContentMutation $mutation): ContentRecord
    {
        $id = $this->nextId++;
        $now = gmdate(DATE_ATOM);
        $record = $this->make($id, $mutation, $now, $now);
        $this->records[$id] = $record;
        return $record;
    }

    public function update(int $id, ContentMutation $mutation): ContentRecord
    {
        $current = $this->records[$id] ?? null;
        if ($current === null) {
            throw new \RuntimeException('Content not found: ' . $id);
        }

        $record = $this->make($id, $mutation, $current->createdAt, gmdate(DATE_ATOM), $current->terms);
        $this->records[$id] = $record;
        return $record;
    }

    public function find(
        int $id,
        ContentProjection $projection = ContentProjection::Detail,
    ): ?ContentRecord {
        $record = $this->records[$id] ?? null;

        return $record?->project($projection);
    }

    public function findPublishedBySlug(
        int $siteId,
        string $type,
        string $locale,
        string $slug,
    ): ?ContentRecord {
        foreach ($this->records as $record) {
            if (
                $record->siteId === $siteId
                && $record->type === $type
                && $record->locale === $locale
                && $record->slug === $slug
                && $record->status === ContentStatus::Published
            ) {
                return $record;
            }
        }

        return null;
    }

    public function findMany(
        array $ids,
        ContentProjection $projection = ContentProjection::List,
    ): array {
        $result = [];
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            $record = $this->records[$id] ?? null;
            if ($record !== null) {
                $result[$id] = $record->project($projection);
            }
        }

        return $result;
    }

    public function search(ContentQuery $query): array
    {
        $records = array_values(array_filter(
            $this->records,
            function (ContentRecord $record) use ($query): bool {
                if ($record->siteId !== $query->siteId) return false;
                if ($query->type !== null && $record->type !== $query->type) return false;
                if ($query->status !== null && $record->status !== $query->status) return false;
                if ($query->locale !== null && $record->locale !== $query->locale) return false;
                if ($query->authorId !== null && $record->authorId !== $query->authorId) return false;
                if ($query->parentId !== null && $record->parentId !== $query->parentId) return false;
                if ($query->termId !== null && !$this->hasTerm($record, $query->termId, $query->taxonomy)) return false;
                if ($query->beforeId !== null && $record->id >= $query->beforeId) return false;
                if ($query->search !== null && $query->search !== '') {
                    $needle = strtolower($query->search);
                    if (
                        !str_contains(strtolower($record->title), $needle)
                        && !str_contains(strtolower($record->excerpt), $needle)
                        && !str_contains(strtolower($record->slug), $needle)
                    ) {
                        return false;
                    }
                }
                return true;
            }
        ));

        usort($records, static fn (ContentRecord $a, ContentRecord $b): int => $b->id <=> $a->id);
        $records = array_slice($records, max(0, $query->offset), max(1, min(500, $query->limit)));

        return array_map(
            static fn (ContentRecord $record): ContentRecord => $record->project($query->projection),
            $records,
        );
    }

    public function count(ContentQuery $query): int
    {
        return count(array_filter(
            $this->records,
            function (ContentRecord $record) use ($query): bool {
                if ($record->siteId !== $query->siteId) return false;
                if ($query->type !== null && $record->type !== $query->type) return false;
                if ($query->status !== null && $record->status !== $query->status) return false;
                if ($query->locale !== null && $record->locale !== $query->locale) return false;
                if ($query->authorId !== null && $record->authorId !== $query->authorId) return false;
                if ($query->parentId !== null && $record->parentId !== $query->parentId) return false;
                if ($query->termId !== null && !$this->hasTerm($record, $query->termId, $query->taxonomy)) return false;
                if ($query->beforeId !== null && $record->id >= $query->beforeId) return false;
                if ($query->search !== null && trim($query->search) !== '') {
                    $needle = strtolower(trim($query->search));
                    if (
                        !str_contains(strtolower($record->title), $needle)
                        && !str_contains(strtolower($record->excerpt), $needle)
                        && !str_contains(strtolower($record->slug), $needle)
                    ) return false;
                }
                return true;
            },
        ));
    }

    /** @return list<ContentRecord> */
    public function publishDue(int $limit = 50, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $due = array_values(array_filter(
            $this->records,
            static function (ContentRecord $record) use ($now): bool {
                if ($record->status !== ContentStatus::Scheduled || $record->scheduledAt === null) {
                    return false;
                }

                try {
                    return new DateTimeImmutable($record->scheduledAt) <= $now;
                } catch (\Throwable) {
                    return false;
                }
            },
        ));
        usort($due, static function (ContentRecord $left, ContentRecord $right): int {
            return strcmp((string) $left->scheduledAt, (string) $right->scheduledAt)
                ?: $left->id <=> $right->id;
        });

        $published = [];
        foreach (array_slice($due, 0, max(1, min(500, $limit))) as $record) {
            $publishedAt = $now->format(DATE_ATOM);
            $updated = new ContentRecord(
                $record->id,
                $record->siteId,
                $record->type,
                ContentStatus::Published,
                $record->title,
                $record->slug,
                $record->excerpt,
                $record->authorId,
                $record->parentId,
                $record->locale,
                $record->document,
                $record->metadata,
                $record->revisionId,
                $publishedAt,
                null,
                $record->createdAt,
                $publishedAt,
                $record->fields,
                $record->relations,
                $record->terms,
            );
            // The in-memory repository is single-process; replacing only a
            // still-scheduled record preserves the same compare-and-set rule
            // as the database repository.
            if (($this->records[$record->id] ?? null)?->status !== ContentStatus::Scheduled) {
                continue;
            }
            $this->records[$record->id] = $updated;
            $published[] = $updated;
        }

        return $published;
    }

    public function slugExists(
        int $siteId,
        string $type,
        string $locale,
        string $slug,
        ?int $excludeId = null,
    ): bool {
        foreach ($this->records as $record) {
            if ($excludeId !== null && $record->id === $excludeId) continue;
            if (
                $record->siteId === $siteId
                && $record->type === $type
                && $record->locale === $locale
                && $record->slug === $slug
            ) {
                return true;
            }
        }
        return false;
    }

    private function hasTerm(ContentRecord $record, int $termId, ?string $taxonomy): bool
    {
        $taxonomies = $taxonomy !== null ? [$taxonomy] : array_keys($record->terms);
        foreach ($taxonomies as $key) {
            if (in_array($termId, $record->terms[$key] ?? [], true)) return true;
        }
        return false;
    }

    /** @param array<string,list<int>> $terms */
    public function replaceTerms(int $id, array $terms): ContentRecord
    {
        $record = $this->records[$id] ?? null;
        if ($record === null) {
            throw new \RuntimeException('Content not found: ' . $id);
        }

        $updated = new ContentRecord(
            $record->id,
            $record->siteId,
            $record->type,
            $record->status,
            $record->title,
            $record->slug,
            $record->excerpt,
            $record->authorId,
            $record->parentId,
            $record->locale,
            $record->document,
            $record->metadata,
            $record->revisionId,
            $record->publishedAt,
            $record->scheduledAt,
            $record->createdAt,
            gmdate(DATE_ATOM),
            $record->fields,
            $record->relations,
            $terms,
        );
        $this->records[$id] = $updated;
        return $updated;
    }

    /**
     * @param array<string,list<int>> $terms
     */
    private function make(
        int $id,
        ContentMutation $mutation,
        string $createdAt,
        string $updatedAt,
        ?array $terms = null,
    ): ContentRecord {
        return new ContentRecord(
            $id,
            $mutation->siteId,
            $mutation->type,
            $mutation->status,
            $mutation->title,
            $mutation->slug,
            $mutation->excerpt,
            $mutation->authorId,
            $mutation->parentId,
            $mutation->locale,
            $mutation->document,
            $mutation->metadata,
            $mutation->revisionId,
            $mutation->publishedAt,
            $mutation->scheduledAt,
            $createdAt,
            $updatedAt,
            $mutation->fields,
            $mutation->relations,
            $terms ?? $mutation->terms,
        );
    }
}
