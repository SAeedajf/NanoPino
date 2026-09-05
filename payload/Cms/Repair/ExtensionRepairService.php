<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Repair;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Recovery\RecoveryManager;

final readonly class ExtensionRepairService
{
    /** @param list<ExtensionRepairProviderInterface> $providers */
    public function __construct(
        private AuthorizationManager $authorization,
        private RecoveryManager $recovery,
        private array $providers,
    ) {}

    public function inspect(string $extensionId, ?int $actorId = null): ExtensionRepairReport
    {
        $this->authorization->authorize(new AuthorizationRequest('extensions.repair', $actorId));

        $checks = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->inspect($extensionId) as $check) {
                $checks[] = $check;
            }
        }

        return new ExtensionRepairReport($extensionId, $checks);
    }

    /**
     * Repair never runs without a Recovery Point.
     *
     * @param list<string> $checkIds
     * @param callable(string,string,string):void $progress
     */
    public function repair(
        string $extensionId,
        array $checkIds,
        callable $progress,
        ?int $actorId = null,
    ): ExtensionRepairReport {
        $this->authorization->authorize(new AuthorizationRequest('extensions.repair', $actorId));

        $point = $this->recovery->create($extensionId, 'repair', [
            'check_ids' => array_values(array_unique($checkIds)),
        ]);
        $progress('snapshot', 'ok', 'Recovery point created before repair.');

        foreach ($this->providers as $provider) {
            $provider->repair($extensionId, $checkIds, $progress);
        }

        $progress('repair', 'ok', 'Repair providers completed.');
        $report = $this->inspect($extensionId, $actorId);

        if ($report->hasFailures()) {
            $progress('verify', 'warning', 'Repair completed but some checks still fail.');
        } else {
            $progress('verify', 'ok', 'Repair verification passed.');
        }

        return $report;
    }
}
