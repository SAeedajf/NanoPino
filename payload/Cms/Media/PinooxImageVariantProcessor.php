<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

use Pinoox\Portal\Image;
use RuntimeException;

final class PinooxImageVariantProcessor implements MediaVariantProcessorInterface
{
    public function __construct(
        private readonly MediaRepositoryInterface $repository,
        private readonly NativeFileGatewayInterface $files,
        private readonly MediaUploadValidator $validator = new MediaUploadValidator(),
    ) {}

    public function generate(
        MediaAsset $asset,
        ValidatedMediaUpload $source,
        array $specs,
        bool $public = true,
    ): array {
        if ($asset->kind !== MediaKind::Image || $source->kind !== MediaKind::Image) {
            throw new MediaValidationException('Image variants require an image source.');
        }

        $created = [];
        $nativeIds = [];
        $variantKeys = [];

        try {
            foreach ($specs as $spec) {
                if (!$spec instanceof MediaVariantSpec) {
                    throw new MediaValidationException('Variant plan contains invalid specification.');
                }

                $temp = $this->tempPath($spec->extension());

                try {
                    $image = Image::read($source->path);

                    $image = match ($spec->mode) {
                        MediaVariantMode::ScaleDown => $image->scaleDown(
                            width: $spec->width,
                            height: $spec->height,
                        ),
                        MediaVariantMode::CoverDown => $image->coverDown(
                            $spec->width,
                            $spec->height ?? $spec->width,
                            $spec->position,
                        ),
                        MediaVariantMode::Crop => $image->crop(
                            $spec->width,
                            $spec->height ?? $spec->width,
                            $spec->offsetX,
                            $spec->offsetY,
                            position: $spec->position,
                        ),
                    };

                    $encoded = match ($spec->extension()) {
                        'jpg' => $image->toJpeg($spec->quality, progressive: true, strip: true),
                        'webp' => $image->toWebp($spec->quality, strip: true),
                        'png' => $image->toPng(),
                        default => throw new MediaValidationException('Unsupported variant encoder.'),
                    };
                    $encoded->save($temp);

                    $validated = $this->validator->validate(
                        new MediaUploadCandidate(
                            $temp,
                            $asset->id . '-' . $spec->key . '.' . $spec->extension(),
                        )
                    );

                    $native = $this->files->store(
                        $validated,
                        $asset->siteId,
                        $public,
                        [
                            'cms_media_parent_id' => $asset->id,
                            'cms_variant_key' => $spec->key,
                            'cms_variant_mode' => $spec->mode->value,
                            'cms_variant_quality' => $spec->quality,
                        ],
                    );
                    $nativeIds[] = $native->id;

                    $variant = $this->repository->addVariant(
                        $asset->id,
                        $spec->key,
                        $native,
                        $validated->width,
                        $validated->height,
                        $validated->size,
                        $validated->mime,
                        [
                            'mode' => $spec->mode->value,
                            'quality' => $spec->quality,
                            'format' => $spec->extension(),
                        ],
                    );
                    $created[] = $variant;
                    $variantKeys[] = $spec->key;
                } finally {
                    if (isset($temp) && is_file($temp)) {
                        @unlink($temp);
                    }
                }
            }
        } catch (\Throwable $error) {
            foreach (array_reverse($variantKeys) as $key) {
                try { $this->repository->removeVariant($asset->id, $key); } catch (\Throwable) {}
            }
            foreach (array_reverse($nativeIds) as $nativeId) {
                try { $this->files->remove($nativeId); } catch (\Throwable) {}
            }
            throw $error;
        }

        return $created;
    }

    private function tempPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'cms_variant_');
        if ($base === false) {
            throw new RuntimeException('Unable to allocate media variant temp file.');
        }

        $path = $base . '.' . $extension;
        @unlink($base);

        return $path;
    }
}
