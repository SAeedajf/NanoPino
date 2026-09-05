<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class BlockRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof BlockDefinition) {
            throw new InvalidArgumentException('BlockRegistry accepts BlockDefinition only.');
        }

        if (preg_match('#^[a-z0-9][a-z0-9._-]{1,63}/[a-z0-9][a-z0-9._-]{1,63}$#', $definition->name) !== 1) {
            throw new InvalidArgumentException('Block name must be namespace/name.');
        }

        if ($definition->identifier() !== 'block:' . $definition->name) {
            throw new InvalidArgumentException('Block identifier must be block:{namespace/name}.');
        }

        if ($definition->schemaVersion < 1) {
            throw new InvalidArgumentException('Block schema version must be >= 1.');
        }

        foreach ($definition->attributes as $key => $attribute) {
            if (!$attribute instanceof BlockAttributeDefinition || $attribute->name !== $key) {
                throw new InvalidArgumentException('Invalid block attribute definition.');
            }
        }

        foreach ($definition->allowedChildren as $child) {
            if ($child !== '*' && preg_match('#^[a-z0-9][a-z0-9._-]{1,63}/[a-z0-9][a-z0-9._-]{1,63}$#', $child) !== 1) {
                throw new InvalidArgumentException('Invalid allowed child block type.');
            }
        }
    }

    public function definition(string $name): ?BlockDefinition
    {
        $definition = $this->get(str_starts_with($name, 'block:') ? $name : 'block:' . $name);
        return $definition instanceof BlockDefinition ? $definition : null;
    }

    /** @return list<BlockDefinition> */
    public function definitions(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn ($item): bool => $item instanceof BlockDefinition,
        ));
    }
}
