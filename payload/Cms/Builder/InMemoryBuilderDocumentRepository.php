<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

final class InMemoryBuilderDocumentRepository implements BuilderDocumentRepositoryInterface
{
    /** @var array<int,BuilderDocumentRecord> */
    private array $records = [];
    private int $nextId = 1;

    public function create(
        BuilderTarget $target,
        BlockDocument $document,
        string $checksum,
        ?int $actorId,
    ): BuilderDocumentRecord {
        if ($this->findByTarget($target) !== null) {
            throw new BuilderConcurrencyException('Builder target already has a document.');
        }

        $now = gmdate(DATE_ATOM);
        $record = new BuilderDocumentRecord(
            $this->nextId++,
            $target,
            BuilderStatus::Draft,
            $document,
            $checksum,
            1,
            $actorId,
            null,
            $now,
            $now,
        );
        $this->records[$record->id] = $record;
        return $record;
    }

    public function find(int $id): ?BuilderDocumentRecord
    {
        return $this->records[$id] ?? null;
    }

    public function findByTarget(BuilderTarget $target): ?BuilderDocumentRecord
    {
        foreach ($this->records as $record) {
            if ($record->target->identifier() === $target->identifier() && $record->target->siteId === $target->siteId) {
                return $record;
            }
        }
        return null;
    }

    /** @return list<BuilderDocumentRecord> */
    public function list(
        int $siteId,
        ?BuilderTargetType $type = null,
        ?string $locale = null,
        int $limit = 100,
        int $offset = 0,
    ): array {
        $items = array_values(array_filter(
            $this->records,
            static fn (BuilderDocumentRecord $record): bool =>
                $record->target->siteId === $siteId
                && ($type === null || $record->target->type === $type)
                && ($locale === null || $record->target->locale === $locale),
        ));
        usort($items, static fn (BuilderDocumentRecord $a, BuilderDocumentRecord $b): int =>
            [$a->target->type->value, $a->target->key, $a->target->locale, $a->id]
            <=> [$b->target->type->value, $b->target->key, $b->target->locale, $b->id]
        );
        return array_slice($items, max(0, $offset), max(1, $limit));
    }

    public function count(
        int $siteId,
        ?BuilderTargetType $type = null,
        ?string $locale = null,
    ): int {
        return count($this->list($siteId, $type, $locale, PHP_INT_MAX, 0));
    }

    public function save(
        int $id,
        BlockDocument $document,
        string $checksum,
        int $expectedVersion,
        BuilderStatus $status,
        ?int $actorId,
        ?string $publishedAt = null,
    ): BuilderDocumentRecord {
        $current = $this->records[$id] ?? null;
        if ($current === null) {
            throw new \RuntimeException('Builder document not found.');
        }
        if ($current->version !== $expectedVersion) {
            throw new BuilderConcurrencyException('Builder document version conflict.');
        }

        $record = new BuilderDocumentRecord(
            $current->id,
            $current->target,
            $status,
            $document,
            $checksum,
            $current->version + 1,
            $actorId,
            $publishedAt,
            $current->createdAt,
            gmdate(DATE_ATOM),
        );
        $this->records[$id] = $record;
        return $record;
    }
}
