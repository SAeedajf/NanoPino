<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;

final class PerformanceBudgetRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof PerformanceBudgetDefinition) {
            throw new \InvalidArgumentException('PerformanceBudgetRegistry accepts only performance budgets.');
        }
    }

    /** @return list<PerformanceBudgetDefinition> */
    public function forProfile(string $profile): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn($d):bool => $d instanceof PerformanceBudgetDefinition && $d->profile === $profile,
        ));
    }
}
