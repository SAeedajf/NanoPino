<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Marketplace;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;

final readonly class MarketplacePackage
{
    /**
     * Marketplace trust metadata is descriptive only.
     * Installation trust MUST come from local PINX verification.
     *
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public ExtensionType $type,
        public string $version,
        public int $versionCode,
        public string $publisher,
        public string $downloadReference,
        public array $tags = [],
    ) {}
}
