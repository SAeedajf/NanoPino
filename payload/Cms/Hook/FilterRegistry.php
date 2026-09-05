<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Hook;

use App\com_pinoox_cms\Cms\Contracts\FilterRegistryInterface;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class FilterRegistry extends AbstractOwnedRegistry implements FilterRegistryInterface
{
    public function forHook(string $hook): array
    {
        $filters = array_values(array_filter(
            $this->all(),
            static fn (OwnedDefinitionInterface $definition): bool =>
                $definition instanceof FilterDefinition && $definition->hook === $hook,
        ));

        usort($filters, static function (OwnedDefinitionInterface $a, OwnedDefinitionInterface $b): int {
            /** @var FilterDefinition $a */
            /** @var FilterDefinition $b */
            return $a->priority <=> $b->priority;
        });

        return $filters;
    }

    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof FilterDefinition) {
            throw new InvalidArgumentException('FilterRegistry accepts only filter definitions.');
        }
    }
}
