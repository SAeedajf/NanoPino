<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Repair;

final readonly class ExtensionRepairReport
{
    /** @param list<RepairCheckResult> $checks */
    public function __construct(
        public string $extensionId,
        public array $checks,
    ) {}

    public function hasFailures(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status === RepairCheckStatus::Fail) return true;
        }
        return false;
    }

    /** @return list<string> */
    public function repairableFailures(): array
    {
        $ids = [];
        foreach ($this->checks as $check) {
            if ($check->status === RepairCheckStatus::Fail && $check->repairable) {
                $ids[] = $check->id;
            }
        }
        return $ids;
    }
}
