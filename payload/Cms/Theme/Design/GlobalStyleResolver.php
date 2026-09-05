<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Settings\SettingScope;
use App\com_pinoox_cms\Cms\Settings\SettingsService;

final readonly class GlobalStyleResolver
{
    public function __construct(
        private SettingsService $settings,
        private DeepTokenMerge $merge = new DeepTokenMerge(),
        private DesignSchemaValidator $validator = new DesignSchemaValidator(),
    ) {}

    /**
     * Resolution order:
     * theme-scoped override → site-scoped override → ephemeral preview override
     *
     * @param array<string,mixed> $previewOverrides
     * @return array<string,mixed>
     */
    public function overrides(
        int $siteId,
        string $themeReference,
        ?int $actorId = null,
        array $previewOverrides = [],
    ): array {
        $theme = $this->settings->get(
            'theme.design.overrides',
            new SettingScope(ScopeType::Theme, $themeReference),
            $actorId,
            false,
        );
        $site = $this->settings->get(
            'theme.design.overrides',
            new SettingScope(ScopeType::Site, $siteId),
            $actorId,
            false,
        );

        $theme = is_array($theme) ? $theme : [];
        $site = is_array($site) ? $site : [];
        $previewOverrides = $previewOverrides !== []
            ? $this->validator->validate(['schema' => 1, 'tokens' => $previewOverrides])->tokens
            : [];

        return $this->merge->merge(
            $this->merge->merge($theme, $site),
            $previewOverrides,
        );
    }
}
