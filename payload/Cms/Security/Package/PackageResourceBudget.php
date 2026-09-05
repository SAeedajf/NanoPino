<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Package;

final readonly class PackageResourceBudget
{
    public function __construct(
        public int $maxEntries = 10000,
        public int $maxUncompressedBytes = 536_870_912,
        public int $maxSingleFileBytes = 134_217_728,
        public float $maxCompressionRatio = 200.0,
    ) {
        if (
            $maxEntries < 1 || $maxEntries > 100000
            || $maxUncompressedBytes < 1
            || $maxSingleFileBytes < 1
            || $maxSingleFileBytes > $maxUncompressedBytes
            || $maxCompressionRatio < 1.0
            || $maxCompressionRatio > 10000.0
        ) {
            throw new \InvalidArgumentException('Invalid package resource budget.');
        }
    }
}
