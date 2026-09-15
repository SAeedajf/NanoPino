<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

final readonly class WordPressThemeScanResult
{
    /**
     * @param list<string> $files
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $features
     * @param list<string> $dependencies
     * @param list<array{code:string,severity:string,message:string,path?:string}> $issues
     * @param array<string,int> $scores
     * @param list<string> $nextActions
     */
    public function __construct(
        public string $root,
        public WordPressThemeType $type,
        public bool $safeToImport,
        public array $files,
        public array $metadata,
        public array $features,
        public array $dependencies,
        public array $issues,
        public array $scores,
        public array $nextActions,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'root' => $this->root,
            'theme_type' => $this->type->value,
            'safe_to_import' => $this->safeToImport,
            'files' => $this->files,
            'metadata' => $this->metadata,
            'features' => $this->features,
            'dependencies' => $this->dependencies,
            'issues' => $this->issues,
            'scores' => $this->scores,
            'next_actions' => $this->nextActions,
        ];
    }
}
