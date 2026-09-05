<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Contracts;

interface HealthCheckInterface extends OwnedDefinitionInterface
{
    /** @return array{status:string,message?:string,details?:array<string,mixed>} */
    public function run(): array;
}
