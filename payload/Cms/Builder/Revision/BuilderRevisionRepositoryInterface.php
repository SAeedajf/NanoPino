<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Revision;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

interface BuilderRevisionRepositoryInterface
{
    public function append(
        int $builderId,
        BuilderRevisionKind $kind,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
        ?int $sourceRevisionId = null,
    ): BuilderRevisionRecord;

    public function find(int $revisionId): ?BuilderRevisionRecord;

    /** @return list<BuilderRevisionRecord> */
    public function forBuilder(int $builderId, int $limit = 100): array;

    public function latestAutosave(int $builderId, ?int $actorId): ?BuilderRevisionRecord;
    public function latestPublished(int $builderId): ?BuilderRevisionRecord;
}
