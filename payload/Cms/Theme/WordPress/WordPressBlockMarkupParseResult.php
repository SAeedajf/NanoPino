<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

final readonly class WordPressBlockMarkupParseResult
{
    /**
     * @param list<array{code:string,severity:string,message:string,block?:string}> $issues
     * @param list<string> $unsupportedBlocks
     */
    public function __construct(
        public BlockDocument $document,
        public array $issues = [],
        public array $unsupportedBlocks = [],
        public string $sourceSha256 = '',
    ) {}

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
            'source_sha256' => $this->sourceSha256,
            'document' => $this->document->toArray(),
            'issues' => $this->issues,
            'unsupported_blocks' => $this->unsupportedBlocks,
        ];
    }
}
