<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

final readonly class ContentMutation
{
    /**
     * @param array<string,mixed> $document
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $fields
     * @param array<string,list<int>> $relations
     * @param array<string,list<int>> $terms
     */
    public function __construct(
        public int $siteId,
        public string $type,
        public ContentStatus $status,
        public string $title,
        public string $slug,
        public string $excerpt,
        public ?int $authorId,
        public ?int $parentId,
        public string $locale,
        public array $document = [],
        public array $metadata = [],
        public ?int $revisionId = null,
        public ?string $publishedAt = null,
        public ?string $scheduledAt = null,
        public array $fields = [],
        public array $relations = [],
        public array $terms = [],
    ) {}
}
