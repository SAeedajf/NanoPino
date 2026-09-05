<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Package;

use App\com_pinoox_cms\Cms\Exception\ManifestValidationException;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifestValidator;
use App\com_pinoox_cms\Cms\Theme\CmsThemeProfileFactory;

/**
 * Pure pre-sign projection boundary for PINX theme builds.
 *
 * theme.php remains the author's source of truth. Native PinxManifest currently
 * whitelists theme metadata through ThemeManifest::toPinxThemeMeta(), which
 * excludes the CMS profile. This enricher copies the already-authored cms profile
 * into the generated PINX manifest BEFORE checksum/signature generation.
 */
final readonly class PinxCmsThemeManifestEnricher
{
    public function __construct(
        private ExtensionManifestValidator $extensions = new ExtensionManifestValidator(),
        private CmsThemeProfileFactory $profiles = new CmsThemeProfileFactory(),
    ) {}

    /**
     * @param array<string,mixed> $basePinxManifest
     * @param array<string,mixed> $themeRawMeta Raw theme.php data already loaded by trusted builder code.
     * @return array<string,mixed>
     */
    public function enrich(array $basePinxManifest, array $themeRawMeta): array
    {
        if (($basePinxManifest['type'] ?? null) !== 'theme') {
            throw new ManifestValidationException(['PINX CMS theme enrichment requires type=theme.']);
        }

        $cms = $themeRawMeta['cms'] ?? null;
        if (!is_array($cms)) {
            throw new ManifestValidationException(['theme.php does not declare a cms profile.']);
        }

        // Validates theme-specific paths/features independently from extension metadata.
        $this->profiles->fromNativeMeta($themeRawMeta);

        $enriched = $basePinxManifest;
        $enriched['cms'] = $cms;

        $themeMeta = is_array($enriched['theme_meta'] ?? null) ? $enriched['theme_meta'] : [];
        $themeMeta['cms'] = $cms;
        $enriched['theme_meta'] = $themeMeta;

        // Validates the full signed CMS extension profile, including publisher,
        // extension_type=theme, requirements, dependencies and permissions.
        $this->extensions->validate($enriched);

        return $enriched;
    }
}
