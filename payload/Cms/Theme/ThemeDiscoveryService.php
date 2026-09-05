<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

final class ThemeDiscoveryService
{
    public function __construct(
        private readonly NativeThemeGatewayInterface $native,
        private readonly ThemeRegistry $registry,
        private readonly CmsThemeProfileFactory $profiles = new CmsThemeProfileFactory(),
    ) {}

    /** @return list<ThemeDefinition> */
    public function discover(string $package): array
    {
        $result = [];
        foreach ($this->native->discover($package) as $theme) {
            $profile = $this->profiles->fromNativeMeta($theme->raw);
            $rawCms = is_array($theme->raw['cms'] ?? null) ? $theme->raw['cms'] : [];
            $normalizedCms = $rawCms;
            $normalizedCms['schema'] = $profile->schemaVersion;
            $normalizedCms['theme'] = [
                'minimum_cms' => $profile->minimumCms,
                'maximum_cms' => $profile->maximumCms,
                'paths' => [
                    'design' => $profile->designFile,
                    'templates' => $profile->templateDirectory,
                    'parts' => $profile->partDirectory,
                    'patterns' => $profile->patternDirectory,
                    'variations' => $profile->variationDirectory,
                ],
                'template_extensions' => $profile->templateExtensions,
                'features' => $profile->features,
            ];

            $definition = new ThemeDefinition(
                id: 'theme:' . $theme->package . '/' . $theme->name,
                ownerId: $theme->package . '.theme.' . str_replace('-', '_', $theme->name),
                package: $theme->package,
                name: $theme->name,
                title: $theme->title,
                description: $theme->description,
                version: $theme->version,
                versionCode: $theme->versionCode,
                extends: $theme->extends,
                cover: $theme->cover,
                cms: $normalizedCms,
                raw: $theme->raw,
            );

            if ($this->registry->has($definition->id)) {
                $this->registry->register($definition, true);
            } else {
                $this->registry->register($definition);
            }
            $result[] = $definition;
        }

        return $result;
    }
}
