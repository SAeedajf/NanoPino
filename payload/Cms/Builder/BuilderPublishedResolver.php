<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

use App\com_pinoox_cms\Cms\Builder\Revision\BuilderRevisionRecord;
use App\com_pinoox_cms\Cms\Builder\Revision\BuilderRevisionRepositoryInterface;

final readonly class BuilderPublishedResolver
{
    public function __construct(
        private BuilderDocumentRepositoryInterface $documents,
        private BuilderRevisionRepositoryInterface $revisions,
    ) {}

    public function resolve(BuilderTarget $target): ?BuilderRevisionRecord
    {
        $document = $this->documents->findByTarget($target);
        if ($document === null) {
            return null;
        }

        return $this->revisions->latestPublished($document->id);
    }
}
