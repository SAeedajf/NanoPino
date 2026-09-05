<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Package;

use App\com_pinoox_cms\Cms\Installer\PackageFilePlan;

final readonly class PackagePreflightReport
{
    /** @param list<StaticRiskFinding> $findings */
    public function __construct(
        public PackageFilePlan $filePlan,
        public array $findings = [],
        public ?int $archiveEntryCount = null,
    ) {}

    public function hasHighRisk(): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->severity === StaticRiskSeverity::High) return true;
        }
        return false;
    }

    public function uncompressedBytes(): int
    {
        $total = 0;
        foreach ($this->filePlan->files() as $entry) $total += $entry->size;
        return $total;
    }

    /** @return array<string,mixed> */
    public function publicData(): array
    {
        $counts = ['info'=>0, 'warning'=>0, 'high'=>0];
        $items = [];
        foreach ($this->findings as $finding) {
            $counts[$finding->severity->value]++;
            $items[] = [
                'path' => $finding->path,
                'rule' => $finding->rule,
                'severity' => $finding->severity->value,
                'message' => $finding->message,
            ];
        }

        return [
            'preflight_passed' => true,
            'archive_entries' => $this->archiveEntryCount ?? $this->filePlan->count(),
            'payload_entries' => $this->filePlan->count(),
            'files' => count($this->filePlan->files()),
            'uncompressed_bytes' => $this->uncompressedBytes(),
            'findings' => $items,
            'finding_counts' => $counts,
            'high_risk' => $counts['high'] > 0,
        ];
    }
}
