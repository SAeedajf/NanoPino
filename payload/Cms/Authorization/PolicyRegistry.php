<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class PolicyRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof PolicyDefinition) {
            throw new InvalidArgumentException('PolicyRegistry accepts only policy definitions.');
        }
    }

    /** @return list<PolicyDefinition> */
    public function forCapability(string $capability): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($definition): bool =>
                $definition instanceof PolicyDefinition && $definition->matches($capability)
        ));

        usort($items, static fn (PolicyDefinition $a, PolicyDefinition $b): int =>
            [$a->priority, $a->identifier()] <=> [$b->priority, $b->identifier()]
        );

        return $items;
    }
}
