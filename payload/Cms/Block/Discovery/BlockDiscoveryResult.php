<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Discovery;

final readonly class BlockDiscoveryResult
{
    /**
     * @param list<DiscoveredBlock> $blocks
     * @param list<string> $problems
     */
    public function __construct(
        public array $blocks,
        public array $problems = [],
    ) {}
}
