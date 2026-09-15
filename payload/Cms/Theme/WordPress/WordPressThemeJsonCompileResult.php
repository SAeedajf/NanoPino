<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Theme\Design\DesignDocument;
use App\com_pinoox_cms\Cms\Theme\Design\StyleVariation;

final readonly class WordPressThemeJsonCompileResult
{
    /**
     * @param list<array{code:string,severity:string,message:string,path?:string}> $issues
     */
    public function __construct(
        public string $sourcePath,
        public ?DesignDocument $document,
        public array $issues = [],
        public ?StyleVariation $variation = null,
        public ?int $sourceVersion = null,
    ) {}

    public function safeToUse(): bool
    {
        return $this->document !== null && !$this->hasBlockers();
    }

    public function hasBlockers(): bool
    {
        foreach ($this->issues as $issue) if (($issue['severity'] ?? null) === 'blocker') return true;
        return false;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'source_path' => $this->sourcePath,
            'source_version' => $this->sourceVersion,
            'safe_to_use' => $this->safeToUse(),
            'design' => $this->document?->tokens,
            'variation' => $this->variation === null ? null : [
                'id' => $this->variation->id,
                'title' => $this->variation->title,
                'tokens' => $this->variation->tokens,
                'source_theme_path' => $this->variation->sourceThemePath,
            ],
            'issues' => $this->issues,
        ];
    }
}
