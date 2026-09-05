<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;

final class MediaService
{
    public function __construct(
        private readonly MediaRepositoryInterface $repository,
        private readonly NativeFileGatewayInterface $files,
        private readonly MediaUploadValidator $validator,
        private readonly MediaUploadPolicy $uploadPolicy,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string,mixed> $metadata */
    public function upload(
        MediaUploadCandidate $candidate,
        int $siteId,
        bool $public = true,
        string $title = '',
        string $alt = '',
        string $caption = '',
        string $description = '',
        array $metadata = [],
        ?int $actorId = null,
        ?string $correlationId = null,
    ): MediaAsset {
        $this->authorization->authorize(new AuthorizationRequest(
            'media.upload',
            $actorId,
            ScopeType::Site,
            $siteId,
            'media_collection',
        ));

        $validated = $this->validator->validate($candidate);
        $title = $this->bounded($title !== '' ? $title : pathinfo($validated->originalName, PATHINFO_FILENAME), 255, 'title');
        $alt = $this->bounded($alt, 500, 'alt');
        $caption = $this->bounded($caption, 5000, 'caption');
        $description = $this->bounded($description, 10000, 'description');
        $metadata = $this->metadata(array_replace($metadata, [
            'source_sha256' => $validated->sha256,
        ]));

        $native = $this->files->store(
            $validated,
            $siteId,
            $public,
            ['cms_kind' => $validated->kind->value],
        );

        try {
            $asset = $this->repository->create(
                $siteId,
                $native,
                $validated,
                $title,
                $alt,
                $caption,
                $description,
                $actorId,
                $metadata,
            );
        } catch (\Throwable $error) {
            // Compensate native storage if CMS metadata persistence fails.
            $this->files->remove($native->id);
            throw $error;
        }

        $this->audit->log(
            'media.upload',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $siteId,
            'media',
            $asset->id,
            $correlationId,
            [
                'native_file_id' => $native->id,
                'mime' => $validated->mime,
                'kind' => $validated->kind->value,
                'size' => $validated->size,
                'public' => $public,
            ],
        );

        return $asset;
    }

    public function find(int $id, ?int $actorId = null): ?MediaAsset
    {
        $asset = $this->repository->find($id);
        if ($asset === null || $asset->status === MediaStatus::Deleted) return null;

        $this->authorization->authorize(new AuthorizationRequest(
            'media.read',
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $asset->ownerId,
        ));

        return $asset;
    }

    /** @return list<MediaAsset> */
    public function search(
        int $siteId,
        ?MediaKind $kind = null,
        ?string $query = null,
        int $limit = 100,
        ?int $actorId = null,
        int $offset = 0,
    ): array {
        $this->authorization->authorize(new AuthorizationRequest(
            'media.read',
            $actorId,
            ScopeType::Site,
            $siteId,
            'media_collection',
        ));

        return $this->repository->search($siteId, $kind, $query, $limit, $offset);
    }

    /** @return array{items:list<MediaAsset>,summary:array<string,int>,pagination:array<string,int|bool>,upload_policy:array<string,mixed>} */
    public function library(
        int $siteId,
        ?MediaKind $kind = null,
        ?string $query = null,
        int $limit = 50,
        int $offset = 0,
        ?int $actorId = null,
    ): array {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $total = $this->repository->count($siteId, $kind, $query);
        $items = $this->search($siteId, $kind, $query, $limit + 1, $actorId, $offset);
        $hasMore = count($items) > $limit;
        if ($hasMore) $items = array_slice($items, 0, $limit);

        return [
            'items' => $items,
            'summary' => $this->repository->summary($siteId),
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'returned' => count($items),
                'total' => $total,
                'has_more' => $hasMore,
            ],
            'upload_policy' => $this->uploadPolicy->toArray(),
        ];
    }

    /** @return array{asset:MediaAsset,usages:list<MediaUsage>,variants:list<MediaVariant>,in_use:bool,usage_count:int,variant_count:int} */
    public function details(int $id, ?int $actorId = null): array
    {
        $asset = $this->find($id, $actorId);
        if ($asset === null) {
            throw new MediaValidationException('Media asset not found.');
        }
        $usages = $this->repository->usages($id);
        $variants = $this->repository->variants($id);
        return [
            'asset' => $asset,
            'usages' => $usages,
            'variants' => $variants,
            'in_use' => $usages !== [],
            'usage_count' => count($usages),
            'variant_count' => count($variants),
        ];
    }

    /** @param array<string,mixed> $changes */
    public function updateMetadata(
        int $id,
        array $changes,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): MediaAsset {
        $asset = $this->requireAsset($id);

        $this->authorization->authorize(new AuthorizationRequest(
            'media.update',
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $asset->ownerId,
        ));

        $normalized = [];
        foreach ([
            'title' => 255,
            'alt' => 500,
            'caption' => 5000,
            'description' => 10000,
        ] as $key => $limit) {
            if (array_key_exists($key, $changes)) {
                $normalized[$key] = $this->bounded((string)$changes[$key], $limit, $key);
            }
        }

        foreach (['focal_x', 'focal_y'] as $key) {
            if (array_key_exists($key, $changes)) {
                $value = $changes[$key];
                if ($value !== null) {
                    $value = (float)$value;
                    if ($value < 0 || $value > 1) {
                        throw new MediaValidationException($key . ' must be between 0 and 1.');
                    }
                }
                $normalized[$key] = $value;
            }
        }

        if (array_key_exists('metadata', $changes)) {
            if (!is_array($changes['metadata'])) {
                throw new MediaValidationException('Media metadata must be an object.');
            }
            $normalized['metadata'] = $this->metadata($changes['metadata']);
        }

        $updated = $this->repository->updateMetadata($id, $normalized);

        $this->audit->log(
            'media.update',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $correlationId,
            ['changed' => array_keys($normalized)],
        );

        return $updated;
    }

    public function trackUsage(
        int $mediaId,
        string $resourceType,
        string|int $resourceId,
        string $context,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): MediaUsage {
        $asset = $this->requireAsset($mediaId);

        $this->authorization->authorize(new AuthorizationRequest(
            'media.update',
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $asset->ownerId,
        ));

        $resourceType = $this->identifier($resourceType, 'resource type');
        $context = $this->identifier($context, 'usage context');

        $usage = $this->repository->addUsage(
            $mediaId,
            $asset->siteId,
            $resourceType,
            $resourceId,
            $context,
        );

        $this->audit->log(
            'media.usage.attach',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $correlationId,
            [
                'resource_type' => $resourceType,
                'resource_id' => (string)$resourceId,
                'context' => $context,
            ],
        );

        return $usage;
    }

    public function delete(
        int $id,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): MediaAsset {
        $asset = $this->requireAsset($id);

        $this->authorization->authorize(new AuthorizationRequest(
            'media.delete',
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $asset->ownerId,
        ));

        $usages = $this->repository->usages($id);
        if ($usages !== []) {
            throw new MediaInUseException($usages);
        }

        // Soft-delete metadata first so a crash never leaves a visible asset that
        // points to a missing native file. Native deletion failure is compensated.
        $deleted = $this->repository->setStatus($id, MediaStatus::Deleted);

        if (!$this->files->remove($asset->nativeFileId)) {
            $this->repository->setStatus($id, MediaStatus::Ready);
            throw new \RuntimeException('Native Pinoox file could not be removed.');
        }

        $this->audit->log(
            'media.delete',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $correlationId,
            ['native_file_id' => $asset->nativeFileId],
        );

        return $deleted;
    }

    private function requireAsset(int $id): MediaAsset
    {
        $asset = $this->repository->find($id);
        if ($asset === null || $asset->status === MediaStatus::Deleted) {
            throw new MediaValidationException('Media asset not found.');
        }
        return $asset;
    }

    private function bounded(string $value, int $max, string $label): string
    {
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > $max) {
            throw new MediaValidationException($label . ' exceeds maximum length.');
        }
        return $value;
    }

    /** @param array<string,mixed> $metadata */
    private function metadata(array $metadata): array
    {
        $encoded = json_encode(
            $metadata,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if (strlen($encoded) > 262144) {
            throw new MediaValidationException('Media metadata exceeds 256 KiB.');
        }
        return $metadata;
    }

    private function identifier(string $value, string $label): string
    {
        $value = trim($value);
        if (preg_match('/^[a-z0-9][a-z0-9._:-]{0,127}$/', $value) !== 1) {
            throw new MediaValidationException('Invalid ' . $label . '.');
        }
        return $value;
    }
}
