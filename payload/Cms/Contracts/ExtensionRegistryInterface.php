<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Contracts;

interface ExtensionRegistryInterface extends RegistryInterface
{
    public function extension(string $package): ?ExtensionDefinitionInterface;
}
