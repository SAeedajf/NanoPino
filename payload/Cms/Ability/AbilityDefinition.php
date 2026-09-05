<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use Closure;
use InvalidArgumentException;

final readonly class AbilityDefinition implements OwnedDefinitionInterface
{
    public Closure $executor;

    /**
     * @param callable(array<string,mixed>):mixed $executor
     * @param array<string,mixed> $inputSchema
     * @param array<string,mixed> $outputSchema
     */
    public function __construct(
        private string $id,
        private string $owner,
        callable $executor,
        public string $version = 'v1',
        public ?string $permission = null,
        public array $inputSchema = [],
        public array $outputSchema = [],
        public string $description = '',
        public bool $idempotent = false,
        public bool $audit = true,
    ) {
        new OwnerIdentifier($owner);

        if (preg_match('#^[a-z0-9][a-z0-9._/-]{1,190}$#', $id) !== 1) {
            throw new InvalidArgumentException('Invalid ability identifier: ' . $id);
        }

        $this->executor = Closure::fromCallable($executor);
    }

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->owner; }
}
