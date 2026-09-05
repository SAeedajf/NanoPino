<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class TaxonomyRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof TaxonomyDefinition) {
            throw new InvalidArgumentException('TaxonomyRegistry accepts only TaxonomyDefinition.');
        }
    }

    public function definition(string $key): ?TaxonomyDefinition
    {
        $definition = $this->get($key);
        return $definition instanceof TaxonomyDefinition ? $definition : null;
    }

    /** @return list<TaxonomyDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($item): bool => $item instanceof TaxonomyDefinition,
        ));
        usort($items, static fn (TaxonomyDefinition $a, TaxonomyDefinition $b): int =>
            $a->identifier() <=> $b->identifier()
        );
        return $items;
    }
}
