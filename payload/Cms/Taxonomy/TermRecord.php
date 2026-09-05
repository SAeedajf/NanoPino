<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

final readonly class TermRecord
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $id,
        public int $siteId,
        public string $taxonomy,
        public string $name,
        public string $slug,
        public string $description,
        public ?int $parentId,
        public string $locale,
        public array $metadata,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
