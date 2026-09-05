<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

interface MediaVariantProcessorInterface
{
    /**
     * Generates variants from the already validated local upload source.
     *
     * @param list<MediaVariantSpec> $specs
     * @return list<MediaVariant>
     */
    public function generate(
        MediaAsset $asset,
        ValidatedMediaUpload $source,
        array $specs,
        bool $public = true,
    ): array;
}
