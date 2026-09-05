<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Marketplace;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;

final readonly class MarketplaceQuery
{
    public function __construct(
        public string $search = '',
        public ?ExtensionType $type = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new \InvalidArgumentException('Invalid marketplace pagination.');
        }
        if (strlen($search) > 190) {
            throw new \InvalidArgumentException('Marketplace search is too long.');
        }
    }
}
