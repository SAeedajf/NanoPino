<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final readonly class MediaAsset
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $id,
        public int $siteId,
        public int $nativeFileId,
        public ?string $nativeHashId,
        public MediaKind $kind,
        public string $mime,
        public string $originalName,
        public string $title,
        public string $alt,
        public string $caption,
        public string $description,
        public int $size,
        public ?int $width,
        public ?int $height,
        public ?float $duration,
        public ?float $focalX,
        public ?float $focalY,
        public ?int $ownerId,
        public MediaStatus $status,
        public ?string $url,
        public ?string $thumb,
        public array $metadata,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->siteId,
            'native_file_id' => $this->nativeFileId,
            'native_hash_id' => $this->nativeHashId,
            'kind' => $this->kind->value,
            'mime' => $this->mime,
            'original_name' => $this->originalName,
            'title' => $this->title,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'description' => $this->description,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'focal_x' => $this->focalX,
            'focal_y' => $this->focalY,
            'owner_id' => $this->ownerId,
            'status' => $this->status->value,
            'url' => $this->url,
            'thumb' => $this->thumb,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
