<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

interface ContentRepositoryInterface
{
    public function create(ContentMutation $mutation): ContentRecord;
    public function update(int $id, ContentMutation $mutation): ContentRecord;
    public function find(int $id, ContentProjection $projection = ContentProjection::Detail): ?ContentRecord;

    /** @param list<int> $ids @return array<int,ContentRecord> keyed by id */
    public function findMany(array $ids, ContentProjection $projection = ContentProjection::List): array;

    /** @return list<ContentRecord> */
    public function search(ContentQuery $query): array;

    public function count(ContentQuery $query): int;

    public function slugExists(
        int $siteId,
        string $type,
        string $locale,
        string $slug,
        ?int $excludeId = null,
    ): bool;
}
