<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentValidator;
use App\com_pinoox_cms\Cms\Builder\Command\BuilderCommandInterface;
use App\com_pinoox_cms\Cms\Builder\History\BuilderUndoRedoHistory;

final class BuilderEditorSession
{
    public function __construct(
        private BlockDocument $document,
        private readonly BlockDocumentValidator $validator,
        private readonly BuilderUndoRedoHistory $history = new BuilderUndoRedoHistory(),
        private readonly BlockDocumentSerializer $serializer = new BlockDocumentSerializer(),
    ) {
        $this->validator->validate($document);
    }

    public function document(): BlockDocument
    {
        return $this->document;
    }

    public function checksum(): string
    {
        return $this->serializer->checksum($this->document);
    }

    public function execute(BuilderCommandInterface $command): BlockDocument
    {
        $before = $this->document;
        $after = $command->apply($before);

        $this->validator->validate($after);
        $this->history->record($command->name(), $before, $after);
        $this->document = $after;

        return $this->document;
    }

    public function undo(): BlockDocument
    {
        $candidate = $this->history->undo($this->document);
        $this->validator->validate($candidate);
        return $this->document = $candidate;
    }

    public function redo(): BlockDocument
    {
        $candidate = $this->history->redo($this->document);
        $this->validator->validate($candidate);
        return $this->document = $candidate;
    }

    public function canUndo(): bool { return $this->history->canUndo(); }
    public function canRedo(): bool { return $this->history->canRedo(); }
    public function undoCount(): int { return $this->history->undoCount(); }
    public function redoCount(): int { return $this->history->redoCount(); }
}
