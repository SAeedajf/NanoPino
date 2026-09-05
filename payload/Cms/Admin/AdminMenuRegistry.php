<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;

final class AdminMenuRegistry extends AbstractOwnedRegistry
{
    /** @return list<AdminMenuDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter($this->all(), static fn ($item) => $item instanceof AdminMenuDefinition));
        usort($items, static fn (AdminMenuDefinition $a, AdminMenuDefinition $b) => [$a->section, $a->order, $a->identifier()] <=> [$b->section, $b->order, $b->identifier()]);
        return $items;
    }

    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof AdminMenuDefinition) {
            throw new \InvalidArgumentException('AdminMenuRegistry accepts only AdminMenuDefinition.');
        }
    }
}
