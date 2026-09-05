<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block;

use App\com_pinoox_cms\Cms\Block\Migration\BlockMigrationRegistry;
use RuntimeException;

final class BlockRegistrationValidator
{
    public function __construct(
        private readonly BlockMigrationRegistry $migrations,
    ) {}

    public function validate(BlockDefinition $block): void
    {
        $registered = $this->migrations->forBlock($block->name);

        foreach ($block->migrations as $declaration) {
            $from = (int)($declaration['from'] ?? 0);
            $to = (int)($declaration['to'] ?? 0);

            $found = false;
            foreach ($registered as $migration) {
                if (
                    $migration->owner() === $block->owner()
                    && $migration->fromVersion === $from
                    && $migration->toVersion === $to
                ) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new RuntimeException(sprintf(
                    'Block %s declares migration %d→%d but no matching runtime migration is registered.',
                    $block->name,
                    $from,
                    $to,
                ));
            }
        }

        if ($block->schemaVersion <= 1) {
            return;
        }

        $version = 1;
        $guard = 0;

        while ($version < $block->schemaVersion) {
            if (++$guard > 64) {
                throw new RuntimeException('Block migration validation exceeded safety limit.');
            }

            $next = null;
            foreach ($registered as $migration) {
                if ($migration->owner() === $block->owner() && $migration->fromVersion === $version) {
                    if ($next === null || $migration->toVersion < $next->toVersion) {
                        $next = $migration;
                    }
                }
            }

            if ($next === null || $next->toVersion > $block->schemaVersion) {
                throw new RuntimeException(sprintf(
                    'Block %s has no complete migration chain from v1 to v%d.',
                    $block->name,
                    $block->schemaVersion,
                ));
            }

            $version = $next->toVersion;
        }
    }
}
