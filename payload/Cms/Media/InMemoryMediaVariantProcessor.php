<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final class InMemoryMediaVariantProcessor implements MediaVariantProcessorInterface
{
    private int $nativeId = 5000;

    public function __construct(private readonly MediaRepositoryInterface $repository) {}

    public function generate(
        MediaAsset $asset,
        ValidatedMediaUpload $source,
        array $specs,
        bool $public = true,
    ): array {
        $result = [];
        foreach ($specs as $spec) {
            if (!$spec instanceof MediaVariantSpec) {
                throw new MediaValidationException('Invalid variant spec.');
            }

            $width = min($source->width ?? $spec->width, $spec->width);
            $height = $spec->height;
            if ($spec->mode === MediaVariantMode::ScaleDown && $source->width && $source->height) {
                $ratio = min(1, $spec->width / $source->width);
                if ($spec->height !== null) {
                    $ratio = min($ratio, $spec->height / $source->height);
                }
                $width = max(1, (int)round($source->width * $ratio));
                $height = max(1, (int)round($source->height * $ratio));
            }

            $native = new NativeFileReference(
                $this->nativeId++,
                null,
                $public ? '/variants/' . $asset->id . '/' . $spec->key : null,
                null,
                $source->path,
                'memory',
                $public ? 'public' : 'private',
            );

            $result[] = $this->repository->addVariant(
                $asset->id,
                $spec->key,
                $native,
                $width,
                $height,
                null,
                'image/' . ($spec->extension() === 'jpg' ? 'jpeg' : $spec->extension()),
                ['mode' => $spec->mode->value, 'quality' => $spec->quality],
            );
        }
        return $result;
    }
}
