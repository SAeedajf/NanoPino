<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Repair;

interface ExtensionRepairProviderInterface
{
    public function id(): string;

    /** @return list<RepairCheckResult> */
    public function inspect(string $extensionId): array;

    /**
     * @param list<string> $checkIds
     * @param callable(string,string,string):void $progress
     */
    public function repair(string $extensionId, array $checkIds, callable $progress): void;
}
