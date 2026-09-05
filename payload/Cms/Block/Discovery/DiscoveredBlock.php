<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Discovery;

use App\com_pinoox_cms\Cms\Block\BlockDefinition;

final readonly class DiscoveredBlock
{
    public function __construct(
        public BlockDefinition $definition,
        public string $manifestPath,
    ) {}
}
