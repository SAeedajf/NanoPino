<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

use App\com_pinoox_cms\Cms\Contracts\AbilityRegistryInterface;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class AbilityRegistry extends AbstractOwnedRegistry implements AbilityRegistryInterface
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof AbilityDefinition) {
            throw new InvalidArgumentException('AbilityRegistry accepts only ability definitions.');
        }
    }
}
