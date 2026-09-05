<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class RoleTemplateRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof RoleTemplateDefinition) {
            throw new InvalidArgumentException('RoleTemplateRegistry accepts only role templates.');
        }
    }

    /** @return list<RoleTemplateDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($definition): bool => $definition instanceof RoleTemplateDefinition,
        ));
        usort($items, static fn (RoleTemplateDefinition $a, RoleTemplateDefinition $b): int =>
            $a->identifier() <=> $b->identifier()
        );
        return $items;
    }
}
