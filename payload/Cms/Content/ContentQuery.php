<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

final readonly class ContentQuery
{
    public function __construct(
        public int $siteId = 1,
        public ?string $type = null,
        public ?ContentStatus $status = null,
        public ?string $locale = null,
        public ?int $authorId = null,
        public ?int $parentId = null,
        public ?string $search = null,
        public int $limit = 50,
        public int $offset = 0,
        public ContentProjection $projection = ContentProjection::Detail,
        public ?int $beforeId = null,
    ) {}
}
