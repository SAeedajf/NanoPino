<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Extension;

final readonly class ExtensionCostRecord
{
    public function __construct(
        public string $extensionId,
        public float $bootMs,
        public int $memoryDeltaBytes,
        public int $registrations,
    ) {}
}
