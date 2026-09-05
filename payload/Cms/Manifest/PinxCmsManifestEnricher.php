<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Manifest;

use App\com_pinoox_cms\Cms\Exception\ManifestValidationException;

/**
 * Pure boundary used by the future CMS PINX build coordinator.
 * Stock PinxBuilder currently builds the base manifest without copying app.php[cms].
 * Enrichment must happen before the manifest bytes are signed.
 */
final readonly class PinxCmsManifestEnricher
{
    public function __construct(private ExtensionManifestValidator $validator = new ExtensionManifestValidator())
    {
    }

    /**
     * @param array<string,mixed> $basePinxManifest Manifest produced by native PINX primitives.
     * @param array<string,mixed> $appConfig Already-loaded Pinoox app config.
     * @return array<string,mixed>
     */
    public function enrich(array $basePinxManifest, array $appConfig): array
    {
        $cms = $appConfig['cms'] ?? null;
        if (!is_array($cms)) {
            throw new ManifestValidationException(['app config does not declare a cms profile.']);
        }

        $enriched = $basePinxManifest;
        $enriched['cms'] = $cms;
        $this->validator->validate($enriched);

        return $enriched;
    }
}
