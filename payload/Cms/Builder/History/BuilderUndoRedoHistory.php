<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\History;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;

final class BuilderUndoRedoHistory
{
    /** @var list<BuilderHistoryEntry> */
    private array $undo = [];

    /** @var list<BuilderHistoryEntry> */
    private array $redo = [];

    public function __construct(
        private readonly BlockDocumentSerializer $serializer = new BlockDocumentSerializer(),
        private readonly int $limit = 100,
    ) {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Builder history limit must be between 1 and 1000.');
        }
    }

    public function record(string $command, BlockDocument $before, BlockDocument $after): void
    {
        $beforeChecksum = $this->serializer->checksum($before);
        $afterChecksum = $this->serializer->checksum($after);

        if (hash_equals($beforeChecksum, $afterChecksum)) {
            return;
        }

        $this->undo[] = new BuilderHistoryEntry(
            $command,
            $before,
            $after,
            $beforeChecksum,
            $afterChecksum,
        );

        if (count($this->undo) > $this->limit) {
            array_shift($this->undo);
        }

        $this->redo = [];
    }

    public function canUndo(): bool { return $this->undo !== []; }
    public function canRedo(): bool { return $this->redo !== []; }

    public function undo(BlockDocument $current): BlockDocument
    {
        $entry = array_pop($this->undo);
        if (!$entry instanceof BuilderHistoryEntry) {
            return $current;
        }

        $currentChecksum = $this->serializer->checksum($current);
        if (!hash_equals($entry->afterChecksum, $currentChecksum)) {
            $this->undo[] = $entry;
            throw new \RuntimeException('Undo history diverged from current Builder state.');
        }

        $this->redo[] = $entry;
        return $entry->before;
    }

    public function redo(BlockDocument $current): BlockDocument
    {
        $entry = array_pop($this->redo);
        if (!$entry instanceof BuilderHistoryEntry) {
            return $current;
        }

        $currentChecksum = $this->serializer->checksum($current);
        if (!hash_equals($entry->beforeChecksum, $currentChecksum)) {
            $this->redo[] = $entry;
            throw new \RuntimeException('Redo history diverged from current Builder state.');
        }

        $this->undo[] = $entry;
        return $entry->after;
    }

    public function clear(): void
    {
        $this->undo = [];
        $this->redo = [];
    }

    public function undoCount(): int { return count($this->undo); }
    public function redoCount(): int { return count($this->redo); }
}
