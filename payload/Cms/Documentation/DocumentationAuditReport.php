<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Documentation;

final readonly class DocumentationAuditReport
{
    /**
     * @param list<DocumentationAuditIssue> $issues
     * @param list<string> $declaredReleaseBlockers
     */
    public function __construct(
        public array $issues,
        public int $requiredDocs,
        public int $machineContracts,
        public int $adrCount,
        public int $markdownFiles,
        public int $internalLinks,
        public string $mode = 'source',
        public string $evidenceStatus = 'not-required',
        public array $declaredReleaseBlockers = [],
    ) {
    }

    public function valid(): bool
    {
        return $this->issues === [];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid(),
            'mode' => $this->mode,
            'evidence_status' => $this->evidenceStatus,
            'required_docs' => $this->requiredDocs,
            'machine_contracts' => $this->machineContracts,
            'adr_count' => $this->adrCount,
            'markdown_files' => $this->markdownFiles,
            'internal_links' => $this->internalLinks,
            'declared_release_blockers' => $this->declaredReleaseBlockers,
            'issues' => array_map(
                static fn (DocumentationAuditIssue $issue): array => $issue->toArray(),
                $this->issues,
            ),
        ];
    }
}
