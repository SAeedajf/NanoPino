<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

final readonly class RevisionDiff
{
    /** @param array<string,array{from:mixed,to:mixed}> $changes */
    public function __construct(
        public int $fromRevisionId,
        public int $toRevisionId,
        public array $changes,
    ) {}

    public function changed(): bool
    {
        return $this->changes !== [];
    }
}
