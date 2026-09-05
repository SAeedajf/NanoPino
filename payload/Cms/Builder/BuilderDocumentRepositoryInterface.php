<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

interface BuilderDocumentRepositoryInterface
{
    public function create(
        BuilderTarget $target,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
    ): BuilderDocumentRecord;

    public function find(int $id): ?BuilderDocumentRecord;

    public function findByTarget(BuilderTarget $target): ?BuilderDocumentRecord;

    /** @return list<BuilderDocumentRecord> */
    public function list(
        int $siteId,
        ?BuilderTargetType $type = null,
        ?string $locale = null,
        int $limit = 100,
        int $offset = 0,
    ): array;

    public function count(
        int $siteId,
        ?BuilderTargetType $type = null,
        ?string $locale = null,
    ): int;

    public function save(
        int $id,
        BlockDocument $document,
        string $checksum,
        int $expectedVersion,
        BuilderStatus $status,
        ?int $actorId,
        ?string $publishedAt = null,
    ): BuilderDocumentRecord;
}
