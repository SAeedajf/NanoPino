<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use RuntimeException;

final readonly class RecoveryActionService
{
    public function __construct(
        private AuthorizationManager $authorization,
        private RecoveryManager $recovery,
        private RecoveryPointRepositoryInterface $repository,
        private SafeModeManager $safeMode,
        private SafeModeExitGuardInterface $exitGuard,
    ) {}

    public function restore(string $recoveryPointId, ?int $actorId = null): RecoveryPoint
    {
        $this->authorization->authorize(new AuthorizationRequest('system.recovery', $actorId));

        $point = $this->repository->find($recoveryPointId);
        if ($point === null) {
            throw new RuntimeException('Recovery point not found.');
        }

        $restored = $this->recovery->restore($recoveryPointId);

        $state = $this->safeMode->state();
        if ($state->enabled && $state->recoveryPointId === $recoveryPointId) {
            // Keep Safe Mode enabled after restore. Health validation must explicitly clear it.
            $this->safeMode->enable(
                'Recovery point restored; health validation is required before Safe Mode exit.',
                $restored->extensionId,
                $restored->id,
            );
        }

        return $restored;
    }

    public function disableSafeMode(?int $actorId = null): SafeModeState
    {
        $this->authorization->authorize(new AuthorizationRequest('system.recovery', $actorId));

        $state = $this->safeMode->state();
        if (!$state->enabled) {
            return $state;
        }

        if (!$this->exitGuard->canDisable($state)) {
            throw new RuntimeException(
                'Safe Mode exit denied: ' . implode(' | ', $this->exitGuard->reasons($state))
            );
        }

        return $this->safeMode->disable();
    }
}
