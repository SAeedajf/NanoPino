<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Extension;

use App\com_pinoox_cms\Cms\Contracts\ExtensionDefinitionInterface;
use App\com_pinoox_cms\Cms\Contracts\ExtensionRegistryInterface;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class ExtensionRegistry extends AbstractOwnedRegistry implements ExtensionRegistryInterface
{
    public function extension(string $identifier): ?ExtensionDefinitionInterface
    {
        $definition = $this->get($identifier);

        return $definition instanceof ExtensionDefinitionInterface ? $definition : null;
    }

    /** @return list<ExtensionDefinitionInterface> */
    public function byPackage(string $package): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn ($definition): bool => $definition instanceof ExtensionDefinitionInterface
                && $definition->package() === $package,
        ));
    }

    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof ExtensionDefinitionInterface) {
            throw new InvalidArgumentException('ExtensionRegistry accepts only extension definitions.');
        }
    }
}
