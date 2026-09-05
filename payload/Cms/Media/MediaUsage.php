<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final readonly class MediaUsage
{
    public function __construct(
        public int $id,
        public int $mediaId,
        public int $siteId,
        public string $resourceType,
        public string|int $resourceId,
        public string $context,
        public string $createdAt,
    ) {}
}
