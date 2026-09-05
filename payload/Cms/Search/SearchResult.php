<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final readonly class SearchResult
{
    /** @param list<SearchHit> $hits */
    public function __construct(
        public array $hits,
        public int $total,
        public string $driver,
        public bool $degraded = false,
    ) {}
}
