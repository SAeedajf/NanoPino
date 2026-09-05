<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

final readonly class ContentRecord
{
    /**
     * @param array<string,mixed> $document
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $fields
     * @param array<string,list<int>> $relations
     * @param array<string,list<int>> $terms
     */
    public function __construct(
        public int $id,
        public int $siteId,
        public string $type,
        public ContentStatus $status,
        public string $title,
        public string $slug,
        public string $excerpt,
        public ?int $authorId,
        public ?int $parentId,
        public string $locale,
        public array $document,
        public array $metadata,
        public ?int $revisionId,
        public ?string $publishedAt,
        public ?string $scheduledAt,
        public string $createdAt,
        public string $updatedAt,
        public array $fields = [],
        public array $relations = [],
        public array $terms = [],
    ) {}

    public function project(ContentProjection $projection): self
    {
        if ($projection !== ContentProjection::List) {
            return $this;
        }

        return new self(
            $this->id,
            $this->siteId,
            $this->type,
            $this->status,
            $this->title,
            $this->slug,
            $this->excerpt,
            $this->authorId,
            $this->parentId,
            $this->locale,
            [],
            [],
            $this->revisionId,
            $this->publishedAt,
            $this->scheduledAt,
            $this->createdAt,
            $this->updatedAt,
            [],
            [],
            [],
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->siteId,
            'type' => $this->type,
            'status' => $this->status->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'author_id' => $this->authorId,
            'parent_id' => $this->parentId,
            'locale' => $this->locale,
            'document' => $this->document,
            'metadata' => $this->metadata,
            'revision_id' => $this->revisionId,
            'published_at' => $this->publishedAt,
            'scheduled_at' => $this->scheduledAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'fields' => $this->fields,
            'relations' => $this->relations,
            'terms' => $this->terms,
        ];
    }
}
