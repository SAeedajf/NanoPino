<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use App\com_pinoox_cms\Cms\ExtensionCenter\Marketplace\MarketplaceUpdateCandidate;
use App\com_pinoox_cms\Cms\ExtensionCenter\Trust\PackageTrustReport;

final readonly class ExtensionCenterItem
{
    /** @param list<ExtensionProblem> $problems */
    public function __construct(
        public string $id,
        public string $package,
        public string $name,
        public ExtensionType $type,
        public string $version,
        public string $publisher,
        public ExtensionCenterStatus $status,
        public array $problems = [],
        public ?PackageTrustReport $trust = null,
        public ?MarketplaceUpdateCandidate $update = null,
        public bool $developerMode = false,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'package' => $this->package,
            'name' => $this->name,
            'type' => $this->type->value,
            'version' => $this->version,
            'publisher' => $this->publisher,
            'status' => $this->status->value,
            'updateAvailable' => $this->update !== null,
            'availableVersion' => $this->update?->availableVersion,
            'trust' => $this->trust === null ? null : [
                'level' => $this->trust->level->value,
                'integrityVerified' => $this->trust->integrityVerified,
                'authenticityVerified' => $this->trust->authenticityVerified,
                'publisher' => $this->trust->publisher,
                'assurance' => $this->trust->assuranceLabel(),
                'warnings' => $this->trust->warnings,
            ],
            'problems' => array_map(
                static fn (ExtensionProblem $problem): array => [
                    'code' => $problem->code,
                    'severity' => strtolower($problem->severity->name),
                    'message' => $problem->message,
                    'blocking' => $problem->blocking,
                    'metadata' => $problem->metadata,
                ],
                $this->problems,
            ),
            'developerMode' => $this->developerMode,
        ];
    }
}
