<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Migration;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class BlockMigrationRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof BlockMigrationDefinition) {
            throw new InvalidArgumentException('BlockMigrationRegistry accepts BlockMigrationDefinition only.');
        }

        if (preg_match('#^[a-z0-9][a-z0-9._-]{1,63}/[a-z0-9][a-z0-9._-]{1,63}$#', $definition->blockType) !== 1) {
            throw new InvalidArgumentException('Invalid block migration type.');
        }

        if ($definition->fromVersion < 1 || $definition->toVersion <= $definition->fromVersion) {
            throw new InvalidArgumentException('Block migration versions are invalid.');
        }

        $expected = 'block-migration:' . $definition->blockType . ':' . $definition->fromVersion . '-' . $definition->toVersion;
        if ($definition->id !== $expected) {
            throw new InvalidArgumentException('Block migration identifier is not canonical.');
        }
    }

    /** @return list<BlockMigrationDefinition> */
    public function forBlock(string $blockType): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($item): bool =>
                $item instanceof BlockMigrationDefinition && $item->blockType === $blockType,
        ));

        usort($items, static fn (BlockMigrationDefinition $a, BlockMigrationDefinition $b): int =>
            [$a->fromVersion, $a->toVersion, $a->identifier()]
            <=>
            [$b->fromVersion, $b->toVersion, $b->identifier()]
        );

        return $items;
    }
}
