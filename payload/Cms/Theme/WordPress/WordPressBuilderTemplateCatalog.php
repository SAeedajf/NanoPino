<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePattern;

final readonly class WordPressBuilderTemplateCatalog
{
    /**
     * @param list<WordPressBuilderTemplateCandidate> $templates
     * @param list<WordPressBuilderTemplateCandidate> $parts
     * @param list<ThemePattern> $patterns
     * @param list<array{code:string,severity:string,message:string,path?:string}> $issues
     */
    public function __construct(
        public array $templates = [],
        public array $parts = [],
        public array $patterns = [],
        public array $issues = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'templates' => array_map(static fn (WordPressBuilderTemplateCandidate $item): array => $item->toArray(), $this->templates),
            'parts' => array_map(static fn (WordPressBuilderTemplateCandidate $item): array => $item->toArray(), $this->parts),
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
