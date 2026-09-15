<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePattern;

final readonly class WordPressThemeStructureReport
{
    /**
     * @param list<WordPressConvertedTemplate> $templates
     * @param list<WordPressConvertedTemplate> $parts
     * @param list<ThemePattern> $patterns
     * @param list<array{code:string,severity:string,message:string,path?:string}> $issues
     */
    public function __construct(
        public string $themeRoot,
        public array $templates = [],
        public array $parts = [],
        public array $patterns = [],
        public array $issues = [],
    ) {}

    public function hasBlockers(): bool
    {
        foreach ($this->issues as $issue) if (($issue['severity'] ?? null) === 'blocker') return true;
        foreach ([...$this->templates, ...$this->parts] as $item) if ($item->hasBlockers()) return true;
        return false;
    }

    public function safeToUse(): bool
    {
        return !$this->hasBlockers() && ($this->templates !== [] || $this->parts !== [] || $this->patterns !== []);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'theme_root' => $this->themeRoot,
            'safe_to_use' => $this->safeToUse(),
            'templates' => array_map(static fn (WordPressConvertedTemplate $item): array => $item->toArray(), $this->templates),
            'parts' => array_map(static fn (WordPressConvertedTemplate $item): array => $item->toArray(), $this->parts),
            'patterns' => array_map(static fn (ThemePattern $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'categories' => $item->categories,
                'document' => $item->document,
                'source_theme_path' => $item->sourceThemePath,
            ], $this->patterns),
            'issues' => $this->issues,
        ];
    }
}
