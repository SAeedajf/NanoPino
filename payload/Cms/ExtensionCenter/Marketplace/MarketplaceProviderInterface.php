<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Marketplace;

use App\com_pinoox_cms\Cms\Dependency\InstalledExtension;

interface MarketplaceProviderInterface
{
    public function id(): string;
    public function search(MarketplaceQuery $query): MarketplaceSearchResult;

    /**
     * Provider responses are advisory.
     * A downloaded package must still pass canonical local PINX/CMS verification.
     *
     * @param list<InstalledExtension> $installed
     * @return list<MarketplaceUpdateCandidate>
     */
    public function updates(array $installed): array;
}
