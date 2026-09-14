<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use DateTimeImmutable;

interface ContentRepositoryInterface
{
    public function create(ContentMutation $mutation): ContentRecord;
    public function update(int $id, ContentMutation $mutation): ContentRecord;
    public function find(int $id, ContentProjection $projection = ContentProjection::Detail): ?ContentRecord;

    /**
     * Resolve only publicly publishable content by its canonical URL parts.
     * This read path intentionally does not require an authenticated actor.
     */
    public function findPublishedBySlug(
        int $siteId,
        string $type,
        string $locale,
        string $slug,
    ): ?ContentRecord;

    /** @param list<int> $ids @return array<int,ContentRecord> keyed by id */
    public function findMany(array $ids, ContentProjection $projection = ContentProjection::List): array;

    /** @return list<ContentRecord> */
    public function search(ContentQuery $query): array;

    public function count(ContentQuery $query): int;

    /**
     * Atomically publish due scheduled records and return only records claimed
     * by this call. Implementations must guard the status predicate so two
     * scheduler invocations cannot publish the same record twice.
     *
     * @return list<ContentRecord>
     */
    public function publishDue(int $limit = 50, ?DateTimeImmutable $now = null): array;

    public function slugExists(
        int $siteId,
        string $type,
        string $locale,
        string $slug,
        ?int $excludeId = null,
    ): bool;
}
