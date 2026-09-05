<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Permission;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class ExtensionPermissionRegistry extends AbstractOwnedRegistry
{
    public function permission(string $identifier): ?ExtensionPermissionDefinition
    {
        $definition = $this->get($identifier);
        return $definition instanceof ExtensionPermissionDefinition ? $definition : null;
    }

    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof ExtensionPermissionDefinition) {
            throw new InvalidArgumentException('ExtensionPermissionRegistry accepts only extension permission definitions.');
        }
    }
}
