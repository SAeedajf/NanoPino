<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Marketplace;

use App\com_pinoox_cms\Cms\Dependency\InstalledExtension;

final class InMemoryMarketplaceProvider implements MarketplaceProviderInterface
{
    /**
     * @param list<MarketplacePackage> $packages
     * @param list<MarketplaceUpdateCandidate> $updates
     */
    public function __construct(
        private readonly string $providerId,
        private readonly array $packages = [],
        private readonly array $updateItems = [],
    ) {}

    public function id(): string { return $this->providerId; }

    public function search(MarketplaceQuery $query): MarketplaceSearchResult
    {
        $needle = strtolower(trim($query->search));
        $items = array_values(array_filter(
            $this->packages,
            static function (MarketplacePackage $package) use ($query, $needle): bool {
                if ($query->type !== null && $package->type !== $query->type) return false;
                if ($needle === '') return true;

                return str_contains(strtolower(
                    $package->name . ' ' . $package->id . ' ' . $package->publisher . ' ' . implode(' ', $package->tags)
                ), $needle);
            }
        ));

        $offset = ($query->page - 1) * $query->perPage;
        return new MarketplaceSearchResult(
            array_slice($items, $offset, $query->perPage),
            $query->page,
            $query->perPage,
            count($items),
        );
    }

    public function updates(array $installed): array
    {
        $ids = [];
        foreach ($installed as $item) {
            if ($item instanceof InstalledExtension) {
                $ids[$item->identifier] = true;
                $ids[$item->package] = true;
            }
        }

        return array_values(array_filter(
            $this->updateItems,
            static fn (MarketplaceUpdateCandidate $candidate): bool =>
                isset($ids[$candidate->extensionId]),
        ));
    }
}
