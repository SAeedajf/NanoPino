<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final readonly class RecoveryRetentionPolicy
{
    public function __construct(
        public int $maxPerExtension = 5,
        public int $maxAgeDays = 30,
        public int $minimumReadyPoints = 1,
    ) {
        if ($maxPerExtension < 1 || $maxPerExtension > 100) {
            throw new \InvalidArgumentException('Invalid maximum recovery points per extension.');
        }
        if ($maxAgeDays < 1 || $maxAgeDays > 3650) {
            throw new \InvalidArgumentException('Invalid recovery retention age.');
        }
        if ($minimumReadyPoints < 0 || $minimumReadyPoints > $maxPerExtension) {
            throw new \InvalidArgumentException('Invalid minimum ready recovery points.');
        }
    }
}
