<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Contracts;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;

interface ExtensionDefinitionInterface extends OwnedDefinitionInterface
{
    public function package(): string;

    public function type(): ExtensionType;

    public function version(): string;

    public function publisher(): string;
}
