<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Marketplace;

final readonly class MarketplaceSearchResult
{
    /** @param list<MarketplacePackage> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public ?int $total = null,
    ) {}
}
