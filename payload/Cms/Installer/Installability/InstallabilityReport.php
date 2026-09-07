<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer\Installability;

final readonly class InstallabilityReport
{
    /**
     * @param list<InstallabilityFinding> $findings
     * @param array<string,mixed> $evidence
     */
    public function __construct(
        public array $findings,
        public array $evidence = [],
    ) {}

    public function ready(): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->severity === InstallabilitySeverity::Blocker) {
                return false;
            }
        }

        return true;
    }

    /** @return list<InstallabilityFinding> */
    public function blockers(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (InstallabilityFinding $finding): bool =>
                $finding->severity === InstallabilitySeverity::Blocker,
        ));
    }

    /** @return list<InstallabilityFinding> */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (InstallabilityFinding $finding): bool =>
                $finding->severity === InstallabilitySeverity::Warning,
        ));
    }

    public function assertReady(): void
    {
        if ($this->ready()) {
            return;
        }

        $codes = array_map(
            static fn (InstallabilityFinding $finding): string => $finding->code,
            $this->blockers(),
        );

        throw new \RuntimeException(
            'NanoPino installability preflight failed: ' . implode(', ', $codes),
        );
    }

    /** @return array<string,mixed> */
    public function publicData(): array
    {
        return [
            'ready' => $this->ready(),
            'blockers' => count($this->blockers()),
            'warnings' => count($this->warnings()),
            'findings' => array_map(
                static fn (InstallabilityFinding $finding): array => $finding->toArray(),
                $this->findings,
            ),
            'evidence' => $this->evidence,
        ];
    }
}
