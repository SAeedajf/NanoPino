<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

final readonly class WordPressThemeIntakeReport
{
    /**
     * @param list<array{path:string,size:int,sha256:string}> $files
     * @param array<string,mixed> $metadata
     * @param list<array{code:string,severity:string,message:string,path?:string}> $issues
     * @param array<string,mixed>|null $provenance
     * @param array<string,mixed>|null $scan
     */
    public function __construct(
        public string $sourceType,
        public string $source,
        public ?string $themeRoot,
        public array $files,
        public array $metadata,
        public string $manifestSha256,
        public ?string $sourceSha256,
        public array $issues = [],
        public ?array $provenance = null,
        public ?array $scan = null,
    ) {}

    public function hasBlockers(): bool
    {
        foreach ($this->issues as $issue) {
            if (($issue['severity'] ?? null) === 'blocker') return true;
        }
        return false;
    }

    /**
     * This is intentionally weaker than activation readiness. It only says the
     * source may enter the bounded conversion pipeline after blocker review.
     */
    public function safeToConvert(): bool
    {
        return $this->themeRoot !== null && !$this->hasBlockers();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'source_type' => $this->sourceType,
            'source' => $this->source,
            'theme_root' => $this->themeRoot,
            'files' => $this->files,
            'metadata' => $this->metadata,
            'manifest_sha256' => $this->manifestSha256,
            'source_sha256' => $this->sourceSha256,
            'safe_to_convert' => $this->safeToConvert(),
            'issues' => $this->issues,
            'provenance' => $this->provenance,
            'scan' => $this->scan,
        ];
    }
}
