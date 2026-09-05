<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Health;

use App\com_pinoox_cms\Cms\Contracts\HealthCheckInterface;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class HealthCheckRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof HealthCheckInterface) {
            throw new InvalidArgumentException('HealthCheckRegistry accepts only health checks.');
        }
    }
}
