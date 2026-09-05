<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Contracts;

interface OwnedDefinitionInterface
{
    public function identifier(): string;

    public function owner(): string;
}
