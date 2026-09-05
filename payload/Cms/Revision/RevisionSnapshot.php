<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

use App\com_pinoox_cms\Cms\Content\ContentRecord;

final readonly class RevisionSnapshot
{
    /**
     * @param array<string,mixed> $document
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $fields
     * @param array<string,list<int>> $relations
     * @param array<string,list<int>> $terms
     */
    public function __construct(
        public int $contentId,
        public int $siteId,
        public string $contentType,
        public string $status,
        public string $title,
        public string $slug,
        public string $excerpt,
        public ?int $authorId,
        public ?int $parentId,
        public string $locale,
        public array $document,
        public array $metadata,
        public array $fields,
        public array $relations,
        public array $terms,
        public ?string $publishedAt,
        public ?string $scheduledAt,
        public int $schemaVersion = 1,
    ) {}

    public static function fromContent(ContentRecord $content): self
    {
        return new self(
            $content->id,
            $content->siteId,
            $content->type,
            $content->status->value,
            $content->title,
            $content->slug,
            $content->excerpt,
            $content->authorId,
            $content->parentId,
            $content->locale,
            $content->document,
            $content->metadata,
            $content->fields,
            $content->relations,
            $content->terms,
            $content->publishedAt,
            $content->scheduledAt,
        );
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'content_id' => $this->contentId,
            'site_id' => $this->siteId,
            'content_type' => $this->contentType,
            'status' => $this->status,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'author_id' => $this->authorId,
            'parent_id' => $this->parentId,
            'locale' => $this->locale,
            'document' => $this->document,
            'metadata' => $this->metadata,
            'fields' => $this->fields,
            'relations' => $this->relations,
            'terms' => $this->terms,
            'published_at' => $this->publishedAt,
            'scheduled_at' => $this->scheduledAt,
        ];
    }
}
