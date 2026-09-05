<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final class InMemoryMediaRepository implements MediaRepositoryInterface
{
    /** @var array<int,MediaAsset> */
    private array $assets = [];
    /** @var array<int,MediaUsage> */
    private array $usageRecords = [];
    /** @var array<int,MediaVariant> */
    private array $variantRecords = [];
    private int $nextAssetId = 1;
    private int $nextUsageId = 1;
    private int $nextVariantId = 1;

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
    ): MediaAsset {
        $id = $this->nextAssetId++;
        $now = gmdate(DATE_ATOM);
        $asset = new MediaAsset(
            $id, $siteId, $native->id, $native->hashId, $upload->kind, $upload->mime,
            $upload->originalName, $title, $alt, $caption, $description, $upload->size,
            $upload->width, $upload->height, null, null, null, $ownerId,
            MediaStatus::Ready, $native->url, $native->thumb, $metadata, $now, $now,
        );
        $this->assets[$id] = $asset;
        return $asset;
    }

    public function find(int $id): ?MediaAsset
    {
        return $this->assets[$id] ?? null;
    }

    public function search(
        int $siteId,
        ?MediaKind $kind = null,
        ?string $query = null,
        int $limit = 100,
        int $offset = 0,
    ): array {
        $items = array_values(array_filter(
            $this->assets,
            static function (MediaAsset $asset) use ($siteId, $kind, $query): bool {
                if ($asset->siteId !== $siteId || $asset->status === MediaStatus::Deleted) return false;
                if ($kind !== null && $asset->kind !== $kind) return false;
                if ($query !== null && trim($query) !== '') {
                    $needle = strtolower(trim($query));
                    $haystack = implode(' ', [$asset->title, $asset->originalName, $asset->alt, $asset->caption, $asset->mime]);
                    if (!str_contains(strtolower($haystack), $needle)) return false;
                }
                return true;
            }
        ));
        usort($items, static fn (MediaAsset $a, MediaAsset $b): int => $b->id <=> $a->id);
        return array_slice($items, max(0, $offset), max(1, min(501, $limit)));
    }

    public function count(int $siteId, ?MediaKind $kind = null, ?string $query = null): int
    {
        return count(array_filter(
            $this->assets,
            static function (MediaAsset $asset) use ($siteId, $kind, $query): bool {
                if ($asset->siteId !== $siteId || $asset->status === MediaStatus::Deleted) return false;
                if ($kind !== null && $asset->kind !== $kind) return false;
                if ($query !== null && trim($query) !== '') {
                    $needle = strtolower(trim($query));
                    $haystack = implode(' ', [$asset->title, $asset->originalName, $asset->alt, $asset->caption, $asset->mime]);
                    if (!str_contains(strtolower($haystack), $needle)) return false;
                }
                return true;
            },
        ));
    }

    public function summary(int $siteId): array
    {
        $assets = array_values(array_filter(
            $this->assets,
            static fn (MediaAsset $asset): bool => $asset->siteId === $siteId && $asset->status !== MediaStatus::Deleted,
        ));
        $count = static fn (MediaKind $kind): int => count(array_filter($assets, static fn (MediaAsset $asset): bool => $asset->kind === $kind));
        return [
            'total' => count($assets),
            'image' => $count(MediaKind::Image),
            'video' => $count(MediaKind::Video),
            'audio' => $count(MediaKind::Audio),
            'document' => $count(MediaKind::Document),
            'missing_alt' => count(array_filter($assets, static fn (MediaAsset $asset): bool => $asset->kind === MediaKind::Image && trim($asset->alt) === '')),
            'total_bytes' => array_sum(array_map(static fn (MediaAsset $asset): int => $asset->size, $assets)),
        ];
    }

    public function updateMetadata(int $id, array $changes): MediaAsset
    {
        $a = $this->assets[$id] ?? throw new \RuntimeException('Media not found.');
        $asset = new MediaAsset(
            $a->id, $a->siteId, $a->nativeFileId, $a->nativeHashId, $a->kind, $a->mime,
            $a->originalName,
            (string)($changes['title'] ?? $a->title),
            (string)($changes['alt'] ?? $a->alt),
            (string)($changes['caption'] ?? $a->caption),
            (string)($changes['description'] ?? $a->description),
            $a->size, $a->width, $a->height,
            array_key_exists('duration', $changes) ? ($changes['duration'] !== null ? (float)$changes['duration'] : null) : $a->duration,
            array_key_exists('focal_x', $changes) ? ($changes['focal_x'] !== null ? (float)$changes['focal_x'] : null) : $a->focalX,
            array_key_exists('focal_y', $changes) ? ($changes['focal_y'] !== null ? (float)$changes['focal_y'] : null) : $a->focalY,
            $a->ownerId, $a->status, $a->url, $a->thumb,
            is_array($changes['metadata'] ?? null) ? array_replace($a->metadata, $changes['metadata']) : $a->metadata,
            $a->createdAt, gmdate(DATE_ATOM),
        );
        $this->assets[$id] = $asset;
        return $asset;
    }

    public function setStatus(int $id, MediaStatus $status): MediaAsset
    {
        $a = $this->assets[$id] ?? throw new \RuntimeException('Media not found.');
        $asset = new MediaAsset(
            $a->id, $a->siteId, $a->nativeFileId, $a->nativeHashId, $a->kind, $a->mime,
            $a->originalName, $a->title, $a->alt, $a->caption, $a->description,
            $a->size, $a->width, $a->height, $a->duration, $a->focalX, $a->focalY,
            $a->ownerId, $status, $a->url, $a->thumb, $a->metadata,
            $a->createdAt, gmdate(DATE_ATOM),
        );
        $this->assets[$id] = $asset;
        return $asset;
    }

    public function markDeleted(int $id): MediaAsset
    {
        return $this->setStatus($id, MediaStatus::Deleted);
    }

    public function addUsage(
        int $mediaId,
        int $siteId,
        string $resourceType,
        string|int $resourceId,
        string $context,
    ): MediaUsage {
        foreach ($this->usageRecords as $usage) {
            if (
                $usage->mediaId === $mediaId
                && $usage->resourceType === $resourceType
                && (string)$usage->resourceId === (string)$resourceId
                && $usage->context === $context
            ) return $usage;
        }

        $usage = new MediaUsage(
            $this->nextUsageId++, $mediaId, $siteId, $resourceType, $resourceId,
            $context, gmdate(DATE_ATOM),
        );
        $this->usageRecords[$usage->id] = $usage;
        return $usage;
    }

    public function removeUsage(
        int $mediaId,
        string $resourceType,
        string|int $resourceId,
        string $context,
    ): bool {
        foreach ($this->usageRecords as $id => $usage) {
            if (
                $usage->mediaId === $mediaId
                && $usage->resourceType === $resourceType
                && (string)$usage->resourceId === (string)$resourceId
                && $usage->context === $context
            ) {
                unset($this->usageRecords[$id]);
                return true;
            }
        }
        return false;
    }

    public function usages(int $mediaId): array
    {
        return array_values(array_filter(
            $this->usageRecords,
            static fn (MediaUsage $usage): bool => $usage->mediaId === $mediaId
        ));
    }

    public function addVariant(
        int $mediaId,
        string $key,
        NativeFileReference $native,
        ?int $width,
        ?int $height,
        ?int $size,
        ?string $mime,
        array $metadata = [],
    ): MediaVariant {
        foreach ($this->variantRecords as $variant) {
            if ($variant->mediaId === $mediaId && $variant->key === $key) {
                throw new \RuntimeException('Media variant already exists.');
            }
        }
        $variant = new MediaVariant(
            $this->nextVariantId++, $mediaId, $key, $native->id,
            $width, $height, $size, $mime, $metadata, gmdate(DATE_ATOM),
        );
        $this->variantRecords[$variant->id] = $variant;
        return $variant;
    }

    public function removeVariant(int $mediaId, string $key): bool
    {
        foreach ($this->variantRecords as $id => $variant) {
            if ($variant->mediaId === $mediaId && $variant->key === $key) {
                unset($this->variantRecords[$id]);
                return true;
            }
        }
        return false;
    }

    public function variants(int $mediaId): array
    {
        return array_values(array_filter(
            $this->variantRecords,
            static fn (MediaVariant $variant): bool => $variant->mediaId === $mediaId
        ));
    }
}
