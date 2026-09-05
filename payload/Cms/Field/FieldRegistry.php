<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Field;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class FieldRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof FieldDefinition) {
            throw new InvalidArgumentException('FieldRegistry accepts only FieldDefinition.');
        }
    }

    public function definition(string $key): ?FieldDefinition
    {
        $definition = $this->get($key);
        return $definition instanceof FieldDefinition ? $definition : null;
    }

    /** @return list<FieldDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($item): bool => $item instanceof FieldDefinition,
        ));
        usort($items, static fn (FieldDefinition $a, FieldDefinition $b): int =>
            $a->identifier() <=> $b->identifier()
        );
        return $items;
    }
}
