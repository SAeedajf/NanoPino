<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Marketplace;

final readonly class MarketplaceUpdateCandidate
{
    public function __construct(
        public string $extensionId,
        public string $installedVersion,
        public string $availableVersion,
        public int $availableVersionCode,
        public string $downloadReference,
        public string $provider,
    ) {}
}
