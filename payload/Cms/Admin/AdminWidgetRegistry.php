<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;

final class AdminWidgetRegistry extends AbstractOwnedRegistry
{
    /** @return list<AdminWidgetDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter($this->all(), static fn ($item) => $item instanceof AdminWidgetDefinition));
        usort($items, static fn (AdminWidgetDefinition $a, AdminWidgetDefinition $b) => [$a->slot, $a->order, $a->identifier()] <=> [$b->slot, $b->order, $b->identifier()]);
        return $items;
    }

    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof AdminWidgetDefinition) {
            throw new \InvalidArgumentException('AdminWidgetRegistry accepts only AdminWidgetDefinition.');
        }
    }
}
