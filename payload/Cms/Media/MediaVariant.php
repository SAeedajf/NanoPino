<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final readonly class MediaVariant
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $id,
        public int $mediaId,
        public string $key,
        public int $nativeFileId,
        public ?int $width,
        public ?int $height,
        public ?int $size,
        public ?string $mime,
        public array $metadata,
        public string $createdAt,
    ) {}
}
