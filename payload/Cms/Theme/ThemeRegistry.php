<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class ThemeRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof ThemeDefinition) {
            throw new InvalidArgumentException('ThemeRegistry accepts ThemeDefinition only.');
        }

        if (preg_match('/^theme:[a-z0-9._-]+\/[a-z0-9._-]+$/', $definition->identifier()) !== 1) {
            throw new InvalidArgumentException('Invalid theme identifier.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,127}$/', $definition->package) !== 1) {
            throw new InvalidArgumentException('Invalid theme host package.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $definition->name) !== 1) {
            throw new InvalidArgumentException('Invalid theme name.');
        }
    }

    public function definition(string $id): ?ThemeDefinition
    {
        $definition = $this->get($id);
        return $definition instanceof ThemeDefinition ? $definition : null;
    }

    public function byReference(string $package, string $name): ?ThemeDefinition
    {
        return $this->definition('theme:' . $package . '/' . $name);
    }

    /** @return list<ThemeDefinition> */
    public function definitions(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn ($definition): bool => $definition instanceof ThemeDefinition,
        ));
    }
}
