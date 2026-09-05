<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

final readonly class SearchHit
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public ?string $url,
        public float $score,
        public string $excerpt = '',
        public array $metadata = [],
    ) {}
}
