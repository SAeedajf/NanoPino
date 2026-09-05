<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

use App\com_pinoox_cms\Cms\ExtensionCenter\Marketplace\MarketplaceUpdateCandidate;
use App\com_pinoox_cms\Cms\ExtensionCenter\Trust\PackageTrustReport;

final readonly class ExtensionCenterSignals
{
    /** @param list<ExtensionProblem> $problems */
    public function __construct(
        public ExtensionCenterStatus $status = ExtensionCenterStatus::Installed,
        public array $problems = [],
        public ?PackageTrustReport $trust = null,
        public ?MarketplaceUpdateCandidate $update = null,
        public bool $developerMode = false,
    ) {}
}
