<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

final readonly class WordPressConvertedTemplate
{
    /**
     * @param list<array{code:string,severity:string,message:string,path?:string,block?:string}> $issues
     */
    public function __construct(
        public string $kind,
        public string $logicalName,
        public string $sourcePath,
        public BlockDocument $document,
        public array $issues = [],
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
            'kind' => $this->kind,
            'logical_name' => $this->logicalName,
            'source_path' => $this->sourcePath,
            'document' => $this->document->toArray(),
            'issues' => $this->issues,
        ];
    }
}
