<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Revision;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

final class InMemoryBuilderRevisionRepository implements BuilderRevisionRepositoryInterface
{
    /** @var array<int,BuilderRevisionRecord> */
    private array $records = [];
    private int $nextId = 1;

    public function append(
        int $builderId,
        BuilderRevisionKind $kind,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
        ?int $sourceRevisionId = null,
    ): BuilderRevisionRecord {
        $record = new BuilderRevisionRecord(
            $this->nextId++,
            $builderId,
            $kind,
            $document,
            $checksum,
            $actorId,
            $sourceRevisionId,
            gmdate(DATE_ATOM),
        );
        $this->records[$record->id] = $record;
        return $record;
    }

    public function find(int $revisionId): ?BuilderRevisionRecord
    {
        return $this->records[$revisionId] ?? null;
    }

    public function forBuilder(int $builderId, int $limit = 100): array
    {
        $items = array_values(array_filter(
            $this->records,
            static fn (BuilderRevisionRecord $record): bool => $record->builderId === $builderId,
        ));
        usort($items, static fn (BuilderRevisionRecord $a, BuilderRevisionRecord $b): int => $b->id <=> $a->id);
        return array_slice($items, 0, max(1, min(500, $limit)));
    }

    public function latestAutosave(int $builderId, ?int $actorId): ?BuilderRevisionRecord
    {
        foreach ($this->forBuilder($builderId, 500) as $record) {
            if ($record->kind === BuilderRevisionKind::Autosave && $record->actorId === $actorId) {
                return $record;
            }
        }
        return null;
    }

    public function latestPublished(int $builderId): ?BuilderRevisionRecord
    {
        foreach ($this->forBuilder($builderId, 500) as $record) {
            if ($record->kind === BuilderRevisionKind::Published) {
                return $record;
            }
        }
        return null;
    }
}
