<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;

final class AdminRouteRegistry extends AbstractOwnedRegistry
{
    /** @return list<AdminRouteDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter($this->all(), static fn ($item) => $item instanceof AdminRouteDefinition));
        usort($items, static fn (AdminRouteDefinition $a, AdminRouteDefinition $b) => [$a->order, $a->path, $a->identifier()] <=> [$b->order, $b->path, $b->identifier()]);
        return $items;
    }

    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof AdminRouteDefinition) {
            throw new \InvalidArgumentException('AdminRouteRegistry accepts only AdminRouteDefinition.');
        }
    }
}
