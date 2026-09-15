<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

final readonly class WordPressThemeAssetManifest
{
    /**
     * @param list<WordPressThemeAsset> $assets
     * @param list<string> $orderedKeys
     * @param list<array{code:string,severity:string,message:string,path?:string}> $issues
     */
    public function __construct(
        public string $themeRoot,
        public array $assets = [],
        public array $orderedKeys = [],
        public array $issues = [],
    ) {}

    public function hasBlockers(): bool
    {
        foreach ($this->issues as $issue) if (($issue['severity'] ?? null) === 'blocker') return true;
        return false;
    }

    public function safeToUse(): bool
    {
        return $this->assets !== [] && $this->orderedKeys !== [] && !$this->hasBlockers();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'theme_root' => $this->themeRoot,
            'safe_to_use' => $this->safeToUse(),
            'assets' => array_map(static fn (WordPressThemeAsset $asset): array => $asset->toArray(), $this->assets),
            'ordered_keys' => $this->orderedKeys,
            'issues' => $this->issues,
        ];
    }
}
