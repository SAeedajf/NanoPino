<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Binding;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class DataSourceRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof DataSourceDefinition) {
            throw new InvalidArgumentException('DataSourceRegistry accepts DataSourceDefinition only.');
        }

        if (preg_match('/^[a-z][a-z0-9._-]{1,63}$/', $definition->id) !== 1) {
            throw new InvalidArgumentException('Invalid Builder data source ID.');
        }
    }

    public function definition(string $id): ?DataSourceDefinition
    {
        $definition = $this->get($id);
        return $definition instanceof DataSourceDefinition ? $definition : null;
    }
}
