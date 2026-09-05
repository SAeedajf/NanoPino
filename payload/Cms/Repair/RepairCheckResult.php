<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Repair;

final readonly class RepairCheckResult
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $id,
        public RepairCheckStatus $status,
        public string $message,
        public bool $repairable,
        public array $metadata = [],
    ) {}
}
