<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class SettingsRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof SettingDefinition) {
            throw new InvalidArgumentException('SettingsRegistry accepts only SettingDefinition.');
        }
    }

    public function definition(string $key): ?SettingDefinition
    {
        $definition = $this->get($key);
        return $definition instanceof SettingDefinition ? $definition : null;
    }

    /** @return list<SettingDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($definition): bool => $definition instanceof SettingDefinition,
        ));

        usort($items, static fn (SettingDefinition $a, SettingDefinition $b): int =>
            [$a->group, $a->identifier()] <=> [$b->group, $b->identifier()]
        );

        return $items;
    }
}
