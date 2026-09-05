<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;

final class AdminPanelRegistry extends AbstractOwnedRegistry
{
    /** @return list<AdminPanelDefinition> */
    public function definitions(?AdminSurface $surface = null): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($item) => $item instanceof AdminPanelDefinition && ($surface === null || $item->surface === $surface)
        ));
        usort($items, static fn (AdminPanelDefinition $a, AdminPanelDefinition $b) => [$a->surface->value, $a->order, $a->identifier()] <=> [$b->surface->value, $b->order, $b->identifier()]);
        return $items;
    }

    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof AdminPanelDefinition) {
            throw new \InvalidArgumentException('AdminPanelRegistry accepts only AdminPanelDefinition.');
        }
    }
}
