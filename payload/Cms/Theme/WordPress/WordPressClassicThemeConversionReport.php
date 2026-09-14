<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

final readonly class WordPressClassicThemeConversionReport
{
    /**
     * @param list<WordPressConvertedTemplate> $templates
     * @param list<WordPressClassicUnsupportedFeature> $unsupportedFeatures
     * @param list<array{code:string,severity:string,message:string,path?:string}> $issues
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $features
     * @param list<string> $dependencies
     */
    public function __construct(
        public string $themeRoot,
        public WordPressThemeType $themeType,
        public string $isolationMode = 'static-no-execution',
        public array $templates = [],
        public array $unsupportedFeatures = [],
        public array $issues = [],
        public array $metadata = [],
        public array $features = [],
        public array $dependencies = [],
    ) {}

    public function hasBlockers(): bool
    {
        foreach ($this->issues as $issue) if (($issue['severity'] ?? null) === 'blocker') return true;
        foreach ($this->unsupportedFeatures as $feature) if ($feature->severity === 'blocker') return true;
        foreach ($this->templates as $template) if ($template->hasBlockers()) return true;
        return false;
    }

    public function safeToUse(): bool
    {
        return in_array($this->themeType, [WordPressThemeType::Classic, WordPressThemeType::Hybrid], true)
            && $this->templates !== []
            && !$this->hasBlockers();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'theme_root' => $this->themeRoot,
            'theme_type' => $this->themeType->value,
            'isolation_mode' => $this->isolationMode,
            'safe_to_use' => $this->safeToUse(),
            'templates' => array_map(static fn (WordPressConvertedTemplate $template): array => $template->toArray(), $this->templates),
            'unsupported_features' => array_map(static fn (WordPressClassicUnsupportedFeature $feature): array => $feature->toArray(), $this->unsupportedFeatures),
            'issues' => $this->issues,
            'metadata' => $this->metadata,
            'features' => $this->features,
            'dependencies' => $this->dependencies,
        ];
    }
}
