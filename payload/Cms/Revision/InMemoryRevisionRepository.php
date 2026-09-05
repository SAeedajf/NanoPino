<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

final class InMemoryRevisionRepository implements RevisionRepositoryInterface
{
    /** @var array<int,RevisionRecord> */
    private array $records = [];
    private int $nextId = 1;

    public function append(
        RevisionSnapshot $snapshot,
        RevisionKind $kind,
        string $checksum,
        ?int $actorId,
        ?int $sourceRevisionId = null,
    ): RevisionRecord {
        $record = new RevisionRecord(
            $this->nextId++,
            $snapshot,
            $kind,
            $checksum,
            $actorId,
            $sourceRevisionId,
            gmdate(DATE_ATOM),
        );
        $this->records[$record->id] = $record;
        return $record;
    }

    public function find(int $revisionId): ?RevisionRecord
    {
        return $this->records[$revisionId] ?? null;
    }

    public function forContent(int $contentId, int $limit = 100): array
    {
        $records = array_values(array_filter(
            $this->records,
            static fn (RevisionRecord $record): bool =>
                $record->snapshot->contentId === $contentId
        ));
        usort($records, static fn (RevisionRecord $a, RevisionRecord $b): int => $b->id <=> $a->id);
        return array_slice($records, 0, max(1, min(500, $limit)));
    }

    public function latestAutosave(int $contentId, ?int $actorId): ?RevisionRecord
    {
        foreach ($this->forContent($contentId, 500) as $record) {
            if ($record->kind === RevisionKind::Autosave && $record->actorId === $actorId) {
                return $record;
            }
        }
        return null;
    }
}
