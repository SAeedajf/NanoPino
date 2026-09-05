<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Registry;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Contracts\RegistryInterface;
use App\com_pinoox_cms\Cms\Exception\RegistryCollisionException;
use App\com_pinoox_cms\Cms\Exception\RegistryOwnershipException;
use App\com_pinoox_cms\Cms\Support\DefinitionIdentifier;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;

abstract class AbstractOwnedRegistry implements RegistryInterface
{
    /** @var array<string, RegistryEntry> */
    private array $entries = [];

    private int $sequence = 0;

    public function register(OwnedDefinitionInterface $definition, bool $replace = false): void
    {
        $id = (string) new DefinitionIdentifier($definition->identifier());
        $owner = (string) new OwnerIdentifier($definition->owner());
        $existing = $this->entries[$id] ?? null;

        if ($existing !== null) {
            if (!$replace) {
                throw new RegistryCollisionException(sprintf(
                    'Definition "%s" is already registered by "%s".',
                    $id,
                    $existing->definition->owner(),
                ));
            }

            if ($existing->definition->owner() !== $owner) {
                throw new RegistryOwnershipException(sprintf(
                    'Owner "%s" cannot replace definition "%s" owned by "%s".',
                    $owner,
                    $id,
                    $existing->definition->owner(),
                ));
            }
        }

        $this->assertDefinition($definition);
        $this->entries[$id] = new RegistryEntry($definition, ++$this->sequence, microtime(true));
    }

    public function has(string $identifier): bool
    {
        return isset($this->entries[$identifier]);
    }

    public function get(string $identifier): ?OwnedDefinitionInterface
    {
        return $this->entries[$identifier]->definition ?? null;
    }

    public function all(): array
    {
        return array_map(
            static fn (RegistryEntry $entry): OwnedDefinitionInterface => $entry->definition,
            $this->entries,
        );
    }

    public function byOwner(string $owner): array
    {
        new OwnerIdentifier($owner);

        $matches = [];
        foreach ($this->entries as $id => $entry) {
            if ($entry->definition->owner() === $owner) {
                $matches[$id] = $entry->definition;
            }
        }

        return $matches;
    }

    public function remove(string $identifier, ?string $owner = null): bool
    {
        $entry = $this->entries[$identifier] ?? null;
        if ($entry === null) {
            return false;
        }

        if ($owner !== null && $entry->definition->owner() !== $owner) {
            throw new RegistryOwnershipException(sprintf(
                'Owner "%s" cannot remove definition "%s" owned by "%s".',
                $owner,
                $identifier,
                $entry->definition->owner(),
            ));
        }

        unset($this->entries[$identifier]);

        return true;
    }

    public function removeOwner(string $owner): int
    {
        new OwnerIdentifier($owner);

        $removed = 0;
        foreach (array_keys($this->entries) as $id) {
            if ($this->entries[$id]->definition->owner() === $owner) {
                unset($this->entries[$id]);
                ++$removed;
            }
        }

        return $removed;
    }

    public function diagnostics(): array
    {
        $rows = [];
        foreach ($this->entries as $id => $entry) {
            $rows[] = [
                'identifier' => $id,
                'owner' => $entry->definition->owner(),
                'definition' => $entry->definition::class,
                'sequence' => $entry->sequence,
                'registered_at' => $entry->registeredAt,
            ];
        }

        return $rows;
    }

    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
    }
}
