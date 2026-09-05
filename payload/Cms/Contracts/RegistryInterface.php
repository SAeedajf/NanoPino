<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Contracts;

interface RegistryInterface
{
    public function register(OwnedDefinitionInterface $definition, bool $replace = false): void;

    public function has(string $identifier): bool;

    public function get(string $identifier): ?OwnedDefinitionInterface;

    /** @return array<string, OwnedDefinitionInterface> */
    public function all(): array;

    /** @return array<string, OwnedDefinitionInterface> */
    public function byOwner(string $owner): array;

    public function remove(string $identifier, ?string $owner = null): bool;

    public function removeOwner(string $owner): int;

    /** @return list<array<string, mixed>> */
    public function diagnostics(): array;
}
