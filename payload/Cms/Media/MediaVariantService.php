<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;

final class MediaVariantService
{
    public function __construct(
        private readonly MediaRepositoryInterface $repository,
        private readonly MediaUploadValidator $validator,
        private readonly MediaVariantProcessorInterface $processor,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The source must be the same validated original upload represented by the asset.
     * Native-storage materialization for later regeneration is deferred to the storage
     * driver phase, so this method is primarily for upload-time/queue handoff.
     *
     * @param list<MediaVariantSpec> $specs
     * @return list<MediaVariant>
     */
    public function generate(
        int $mediaId,
        MediaUploadCandidate $sourceCandidate,
        array $specs,
        bool $public = true,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): array {
        $asset = $this->repository->find($mediaId);
        if ($asset === null || $asset->status !== MediaStatus::Ready) {
            throw new MediaValidationException('Media asset is not available for variant generation.');
        }

        if ($asset->kind !== MediaKind::Image) {
            throw new MediaValidationException('Responsive variants are supported only for image assets.');
        }

        $this->authorization->authorize(new AuthorizationRequest(
            'media.update',
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $asset->ownerId,
        ));

        if ($specs === [] || count($specs) > 20) {
            throw new MediaValidationException('Variant plan must contain between 1 and 20 variants.');
        }

        $keys = [];
        foreach ($specs as $spec) {
            if (!$spec instanceof MediaVariantSpec) {
                throw new MediaValidationException('Variant plan contains an invalid specification.');
            }
            if (isset($keys[$spec->key])) {
                throw new MediaValidationException('Variant plan contains duplicate key: ' . $spec->key);
            }
            $keys[$spec->key] = true;

            foreach ($this->repository->variants($asset->id) as $existing) {
                if ($existing->key === $spec->key) {
                    throw new MediaValidationException('Variant already exists: ' . $spec->key);
                }
            }
        }

        $source = $this->validator->validate($sourceCandidate);
        $expectedHash = (string)($asset->metadata['source_sha256'] ?? '');
        if ($expectedHash === '' || !hash_equals($expectedHash, $source->sha256)) {
            throw new MediaValidationException(
                'Variant source does not match the original validated media payload.'
            );
        }

        $variants = $this->processor->generate($asset, $source, $specs, $public);

        $this->audit->log(
            'media.variants.generate',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $asset->siteId,
            'media',
            $asset->id,
            $correlationId,
            [
                'variant_keys' => array_map(
                    static fn (MediaVariant $variant): string => $variant->key,
                    $variants,
                ),
                'source_sha256' => $source->sha256,
            ],
        );

        return $variants;
    }
}
