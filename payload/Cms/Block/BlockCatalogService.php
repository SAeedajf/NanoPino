<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;

final class BlockCatalogService
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly AuthorizationManager $authorization,
    ) {}

    /** @return list<BlockDefinition> */
    public function list(int $siteId, ?int $actorId = null): array
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'blocks.read',
            $actorId,
            ScopeType::Site,
            $siteId,
            'block_catalog',
        ));

        return array_values(array_filter(
            $this->blocks->definitions(),
            function (BlockDefinition $block) use ($actorId, $siteId): bool {
                foreach ($block->permissions as $capability) {
                    if (!$this->authorization->can(new AuthorizationRequest(
                        $capability,
                        $actorId,
                        ScopeType::Site,
                        $siteId,
                        'block',
                        $block->name,
                    ))) {
                        return false;
                    }
                }
                return true;
            },
        ));
    }
}
