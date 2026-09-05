<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

interface MediaRepositoryInterface
{
    /** @param array<string,mixed> $metadata */
    public function create(
        int $siteId,
        NativeFileReference $native,
        ValidatedMediaUpload $upload,
        string $title,
        string $alt,
        string $caption,
        string $description,
        ?int $ownerId,
        array $metadata = [],
    ): MediaAsset;

    public function find(int $id): ?MediaAsset;

    /** @return list<MediaAsset> */
    public function search(
        int $siteId,
        ?MediaKind $kind = null,
        ?string $query = null,
        int $limit = 100,
        int $offset = 0,
    ): array;

    public function count(int $siteId, ?MediaKind $kind = null, ?string $query = null): int;

    /** @return array{total:int,image:int,video:int,audio:int,document:int,missing_alt:int,total_bytes:int} */
    public function summary(int $siteId): array;

    /** @param array<string,mixed> $changes */
    public function updateMetadata(int $id, array $changes): MediaAsset;

    public function setStatus(int $id, MediaStatus $status): MediaAsset;
    public function markDeleted(int $id): MediaAsset;

    public function addUsage(
        int $mediaId,
        int $siteId,
        string $resourceType,
        string|int $resourceId,
        string $context,
    ): MediaUsage;

    public function removeUsage(
        int $mediaId,
        string $resourceType,
        string|int $resourceId,
        string $context,
    ): bool;

    /** @return list<MediaUsage> */
    public function usages(int $mediaId): array;

    public function addVariant(
        int $mediaId,
        string $key,
        NativeFileReference $native,
        ?int $width,
        ?int $height,
        ?int $size,
        ?string $mime,
        array $metadata = [],
    ): MediaVariant;

    public function removeVariant(int $mediaId, string $key): bool;

    /** @return list<MediaVariant> */
    public function variants(int $mediaId): array;
}
