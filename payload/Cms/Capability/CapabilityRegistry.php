<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Capability;

use App\com_pinoox_cms\Cms\Contracts\CapabilityRegistryInterface;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class CapabilityRegistry extends AbstractOwnedRegistry implements CapabilityRegistryInterface
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof CapabilityDefinition) {
            throw new InvalidArgumentException('CapabilityRegistry accepts only capability definitions.');
        }
    }

    /** @return list<CapabilityDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($definition): bool => $definition instanceof CapabilityDefinition,
        ));

        usort($items, static fn (CapabilityDefinition $a, CapabilityDefinition $b): int =>
            $a->identifier() <=> $b->identifier()
        );

        return $items;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(
            static fn (CapabilityDefinition $definition): string => $definition->identifier(),
            $this->definitions(),
        );
    }
}
