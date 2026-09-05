<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Contracts;

interface FilterRegistryInterface extends RegistryInterface
{
    /** @return list<OwnedDefinitionInterface> */
    public function forHook(string $hook): array;
}
