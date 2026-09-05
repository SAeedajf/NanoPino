<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Discovery;

use App\com_pinoox_cms\Cms\Block\BlockRegistry;

final class BlockDiscoveryService
{
    public function __construct(
        private readonly DirectoryBlockDiscoverySource $source,
        private readonly BlockRegistry $registry,
    ) {}

    public function discoverAndRegister(
        string $extensionRoot,
        string $owner,
        string $directory = 'blocks',
    ): BlockDiscoveryResult {
        $result = $this->source->discover($extensionRoot, $owner, $directory);

        foreach ($result->blocks as $block) {
            if ($this->registry->has($block->definition->identifier())) {
                $this->registry->register($block->definition, true);
            } else {
                $this->registry->register($block->definition);
            }
        }

        return $result;
    }
}
