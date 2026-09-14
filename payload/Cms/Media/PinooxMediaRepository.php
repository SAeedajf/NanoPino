<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

use App\com_pinoox_cms\Model\MediaAssetModel;
use App\com_pinoox_cms\Model\MediaUsageModel;
use App\com_pinoox_cms\Model\MediaVariantModel;
use App\com_pinoox_cms\Cms\Support\SearchTerm;
use App\com_pinoox_cms\Cms\Support\QueryBounds;

final class PinooxMediaRepository implements MediaRepositoryInterface
{
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
        $model = MediaAssetModel::create([
            'site_id' => $siteId,
            'native_file_id' => $native->id,
            'native_hash_id' => $native->hashId,
            'kind' => $upload->kind->value,
            'mime' => $upload->mime,
            'original_name' => $upload->originalName,
            'title' => $title,
            'alt' => $alt,
            'caption' => $caption,
            'description' => $description,
            'size' => $upload->size,
            'width' => $upload->width,
            'height' => $upload->height,
            'owner_id' => $ownerId,
            'status' => MediaStatus::Ready->value,
            'url' => $native->url,
            'thumb' => $native->thumb,
            'metadata' => $metadata,
        ]);

        return $this->hydrate($model);
    }

    public function find(int $id): ?MediaAsset
    {
        $model = MediaAssetModel::find($id);
        return $model ? $this->hydrate($model) : null;
    }

    public function search(
        int $siteId,
        ?MediaKind $kind = null,
        ?string $query = null,
        int $limit = 100,
        int $offset = 0,
    ): array {
        $builder = MediaAssetModel::query()
            ->where('site_id', $siteId)
            ->where('status', '!=', MediaStatus::Deleted->value);

        if ($kind !== null) {
            $builder->where('kind', $kind->value);
        }

        $needle = SearchTerm::contains($query);
        if ($needle !== null) {
            $builder->where(function ($nested) use ($needle): void {
                $nested
                    ->where('title', 'like', $needle)
                    ->orWhere('original_name', 'like', $needle)
                    ->orWhere('alt', 'like', $needle)
                    ->orWhere('caption', 'like', $needle)
                    ->orWhere('mime', 'like', $needle);
            });
        }

        return $builder
            ->orderByDesc('id')
            ->offset(QueryBounds::offset($offset))
            ->limit(max(1, min(501, $limit)))
            ->get()
            ->map(fn (MediaAssetModel $model): MediaAsset => $this->hydrate($model))
            ->all();
    }

    public function count(int $siteId, ?MediaKind $kind = null, ?string $query = null): int
    {
        $builder = MediaAssetModel::query()
            ->where('site_id', $siteId)
            ->where('status', '!=', MediaStatus::Deleted->value);
        if ($kind !== null) $builder->where('kind', $kind->value);
        $needle = SearchTerm::contains($query);
        if ($needle !== null) {
            $builder->where(function ($nested) use ($needle): void {
                $nested->where('title', 'like', $needle)
                    ->orWhere('original_name', 'like', $needle)
                    ->orWhere('alt', 'like', $needle)
                    ->orWhere('caption', 'like', $needle)
                    ->orWhere('mime', 'like', $needle);
            });
        }
        return (int)$builder->count();
    }

    public function summary(int $siteId): array
    {
        $base = MediaAssetModel::query()
            ->where('site_id', $siteId)
            ->where('status', '!=', MediaStatus::Deleted->value);

        $row = $base
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN kind = ? THEN 1 ELSE 0 END) AS image', [MediaKind::Image->value])
            ->selectRaw('SUM(CASE WHEN kind = ? THEN 1 ELSE 0 END) AS video', [MediaKind::Video->value])
            ->selectRaw('SUM(CASE WHEN kind = ? THEN 1 ELSE 0 END) AS audio', [MediaKind::Audio->value])
            ->selectRaw('SUM(CASE WHEN kind = ? THEN 1 ELSE 0 END) AS document', [MediaKind::Document->value])
            ->selectRaw(
                'SUM(CASE WHEN kind = ? AND (alt IS NULL OR alt = ?) THEN 1 ELSE 0 END) AS missing_alt',
                [MediaKind::Image->value, ''],
            )
            ->selectRaw('COALESCE(SUM(size), 0) AS total_bytes')
            ->first();

        return [
            'total' => (int) ($row?->getAttribute('total') ?? 0),
            'image' => (int) ($row?->getAttribute('image') ?? 0),
            'video' => (int) ($row?->getAttribute('video') ?? 0),
            'audio' => (int) ($row?->getAttribute('audio') ?? 0),
            'document' => (int) ($row?->getAttribute('document') ?? 0),
            'missing_alt' => (int) ($row?->getAttribute('missing_alt') ?? 0),
            'total_bytes' => (int) ($row?->getAttribute('total_bytes') ?? 0),
        ];
    }

    public function updateMetadata(int $id, array $changes): MediaAsset
    {
        $model = MediaAssetModel::find($id)
            ?? throw new \RuntimeException('Media not found.');

        foreach (['title','alt','caption','description','duration','focal_x','focal_y'] as $key) {
            if (array_key_exists($key, $changes)) {
                $model->{$key} = $changes[$key];
            }
        }

        if (is_array($changes['metadata'] ?? null)) {
            $model->metadata = array_replace(
                is_array($model->metadata) ? $model->metadata : [],
                $changes['metadata'],
            );
        }

        $model->save();
        return $this->hydrate($model->fresh());
    }

    public function setStatus(int $id, MediaStatus $status): MediaAsset
    {
        $model = MediaAssetModel::find($id)
            ?? throw new \RuntimeException('Media not found.');
        $model->status = $status->value;
        $model->save();
        return $this->hydrate($model->fresh());
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
        $model = MediaUsageModel::query()->firstOrCreate(
            [
                'media_id' => $mediaId,
                'resource_type' => $resourceType,
                'resource_id' => (string)$resourceId,
                'context' => $context,
            ],
            [
                'site_id' => $siteId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ],
        );

        return $this->hydrateUsage($model);
    }

    public function removeUsage(
        int $mediaId,
        string $resourceType,
        string|int $resourceId,
        string $context,
    ): bool {
        return MediaUsageModel::query()
            ->where('media_id', $mediaId)
            ->where('resource_type', $resourceType)
            ->where('resource_id', (string)$resourceId)
            ->where('context', $context)
            ->delete() > 0;
    }

    public function usages(int $mediaId): array
    {
        return MediaUsageModel::query()
            ->where('media_id', $mediaId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (MediaUsageModel $model): MediaUsage => $this->hydrateUsage($model))
            ->all();
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
        $model = MediaVariantModel::create([
            'media_id' => $mediaId,
            'variant_key' => $key,
            'native_file_id' => $native->id,
            'width' => $width,
            'height' => $height,
            'size' => $size,
            'mime' => $mime,
            'metadata' => $metadata,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->hydrateVariant($model);
    }

    public function removeVariant(int $mediaId, string $key): bool
    {
        return MediaVariantModel::query()
            ->where('media_id', $mediaId)
            ->where('variant_key', $key)
            ->delete() > 0;
    }

    public function variants(int $mediaId): array
    {
        return MediaVariantModel::query()
            ->where('media_id', $mediaId)
            ->orderBy('variant_key')
            ->get()
            ->map(fn (MediaVariantModel $model): MediaVariant => $this->hydrateVariant($model))
            ->all();
    }

    private function hydrate(MediaAssetModel $m): MediaAsset
    {
        return new MediaAsset(
            (int)$m->id,
            (int)$m->site_id,
            (int)$m->native_file_id,
            $m->native_hash_id !== null ? (string)$m->native_hash_id : null,
            MediaKind::from((string)$m->kind),
            (string)$m->mime,
            (string)$m->original_name,
            (string)($m->title ?? ''),
            (string)($m->alt ?? ''),
            (string)($m->caption ?? ''),
            (string)($m->description ?? ''),
            (int)$m->size,
            $m->width !== null ? (int)$m->width : null,
            $m->height !== null ? (int)$m->height : null,
            $m->duration !== null ? (float)$m->duration : null,
            $m->focal_x !== null ? (float)$m->focal_x : null,
            $m->focal_y !== null ? (float)$m->focal_y : null,
            $m->owner_id !== null ? (int)$m->owner_id : null,
            MediaStatus::from((string)$m->status),
            $m->url !== null ? (string)$m->url : null,
            $m->thumb !== null ? (string)$m->thumb : null,
            is_array($m->metadata) ? $m->metadata : [],
            $m->created_at?->format(DATE_ATOM) ?? '',
            $m->updated_at?->format(DATE_ATOM) ?? '',
        );
    }

    private function hydrateUsage(MediaUsageModel $m): MediaUsage
    {
        return new MediaUsage(
            (int)$m->id, (int)$m->media_id, (int)$m->site_id,
            (string)$m->resource_type, (string)$m->resource_id,
            (string)$m->context, (string)$m->created_at,
        );
    }

    private function hydrateVariant(MediaVariantModel $m): MediaVariant
    {
        return new MediaVariant(
            (int)$m->id, (int)$m->media_id, (string)$m->variant_key,
            (int)$m->native_file_id,
            $m->width !== null ? (int)$m->width : null,
            $m->height !== null ? (int)$m->height : null,
            $m->size !== null ? (int)$m->size : null,
            $m->mime !== null ? (string)$m->mime : null,
            is_array($m->metadata) ? $m->metadata : [],
            (string)$m->created_at,
        );
    }
}
