<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Health;

use App\com_pinoox_cms\Cms\Contracts\HealthCheckInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use Closure;

final readonly class HealthCheckDefinition implements HealthCheckInterface
{
    public Closure $check;

    /** @param callable():array{status:string,message?:string,details?:array<string,mixed>} $check */
    public function __construct(
        private string $id,
        private string $owner,
        callable $check,
    ) {
        new OwnerIdentifier($owner);
        $this->check = Closure::fromCallable($check);
    }

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->owner; }
    public function run(): array { return ($this->check)(); }
}
