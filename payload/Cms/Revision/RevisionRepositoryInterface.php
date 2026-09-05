<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

interface RevisionRepositoryInterface
{
    public function append(
        RevisionSnapshot $snapshot,
        RevisionKind $kind,
        string $checksum,
        ?int $actorId,
        ?int $sourceRevisionId = null,
    ): RevisionRecord;

    public function find(int $revisionId): ?RevisionRecord;

    /** @return list<RevisionRecord> */
    public function forContent(int $contentId, int $limit = 100): array;

    public function latestAutosave(int $contentId, ?int $actorId): ?RevisionRecord;
}
