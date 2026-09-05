<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Registry;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final readonly class RegistryEntry
{
    public function __construct(
        public OwnedDefinitionInterface $definition,
        public int $sequence,
        public float $registeredAt,
    ) {
    }
}
