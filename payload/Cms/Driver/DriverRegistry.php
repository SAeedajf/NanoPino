<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Driver;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;

final class DriverRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof DriverDefinition) {
            throw new \InvalidArgumentException('DriverRegistry accepts only DriverDefinition.');
        }
    }

    /** @return list<DriverDefinition> */
    public function byKind(DriverKind $kind): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn ($definition): bool =>
                $definition instanceof DriverDefinition && $definition->kind === $kind,
        ));
    }

    public function resolve(string $id, DriverKind $kind): object
    {
        $definition = $this->get($id);
        if (!$definition instanceof DriverDefinition || $definition->kind !== $kind) {
            throw new \RuntimeException('Driver not found or has incompatible kind: ' . $id);
        }

        return $definition->create();
    }
}
