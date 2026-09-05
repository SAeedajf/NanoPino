<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\GlobalBlock;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Builder\BuilderConcurrencyException;

final class InMemoryGlobalBlockRepository implements GlobalBlockRepositoryInterface
{
    /** @var array<int,GlobalBlockRecord> */
    private array $records = [];
    private int $nextId = 1;

    public function create(
        int $siteId,
        string $name,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
    ): GlobalBlockRecord {
        $now = gmdate(DATE_ATOM);
        $record = new GlobalBlockRecord(
            $this->nextId++,
            $siteId,
            $name,
            $document,
            $checksum,
            1,
            $actorId,
            $now,
            $now,
        );
        $this->records[$record->id] = $record;
        return $record;
    }

    public function find(int $id): ?GlobalBlockRecord
    {
        return $this->records[$id] ?? null;
    }

    public function forSite(int $siteId, int $limit = 200): array
    {
        $items = array_values(array_filter(
            $this->records,
            static fn (GlobalBlockRecord $record): bool => $record->siteId === $siteId,
        ));
        usort($items, static fn (GlobalBlockRecord $a, GlobalBlockRecord $b): int => $b->id <=> $a->id);
        return array_slice($items, 0, max(1, min(500, $limit)));
    }

    public function save(
        int $id,
        string $name,
        BlockDocument $document,
        string $checksum,
        int $expectedVersion,
        ?int $actorId,
    ): GlobalBlockRecord {
        $current = $this->records[$id] ?? null;
        if ($current === null) throw new \RuntimeException('Global Block not found.');
        if ($current->version !== $expectedVersion) {
            throw new BuilderConcurrencyException('Global Block version conflict.');
        }

        $record = new GlobalBlockRecord(
            $current->id,
            $current->siteId,
            $name,
            $document,
            $checksum,
            $current->version + 1,
            $actorId,
            $current->createdAt,
            gmdate(DATE_ATOM),
        );
        $this->records[$id] = $record;
        return $record;
    }
}
