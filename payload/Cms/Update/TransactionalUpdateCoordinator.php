<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Update;

use App\com_pinoox_cms\Cms\Lifecycle\ExtensionState;
use App\com_pinoox_cms\Cms\Recovery\RecoveryManager;
use App\com_pinoox_cms\Cms\Recovery\SafeModeManager;
use Throwable;

final class TransactionalUpdateCoordinator
{
    public function __construct(
        private readonly RecoveryManager $recovery,
        private readonly SafeModeManager $safeMode,
    ) {}

    /** @param list<UpdateStepInterface> $steps */
    public function run(UpdateContext $context, array $steps): UpdateTransactionResult
    {
        $initial = $context->lifecycle->state();
        if (!in_array($initial, [ExtensionState::Active, ExtensionState::Inactive, ExtensionState::Installed], true)) {
            throw new \LogicException('Update requires active, inactive or installed extension state.');
        }

        $point = $this->recovery->create($context->extensionId, 'update', [
            'initial_state' => $initial->value,
        ]);

        $completed = [];
        try {
            $context->lifecycle->transitionTo(ExtensionState::Updating, 'update transaction started');

            foreach ($steps as $step) {
                $completed[] = $step; // partial-safe rollback
                $step->execute($context);
            }

            $context->lifecycle->transitionTo(ExtensionState::Installed, 'update transaction committed');
            return new UpdateTransactionResult(true, $context->lifecycle->state(), $point);
        } catch (Throwable $error) {
            if ($context->lifecycle->canTransitionTo(ExtensionState::Failed)) {
                $context->lifecycle->transitionTo(ExtensionState::Failed, $error->getMessage());
            }
            if ($context->lifecycle->canTransitionTo(ExtensionState::Rollback)) {
                $context->lifecycle->transitionTo(ExtensionState::Rollback, 'update rollback started');
            }

            $rollbackErrors = [];
            foreach (array_reverse($completed) as $step) {
                try {
                    $step->rollback($context);
                } catch (Throwable $rollbackError) {
                    $rollbackErrors[] = $step->id() . ': ' . $rollbackError->getMessage();
                }
            }

            try {
                $this->recovery->restore($point->id);
            } catch (Throwable $restoreError) {
                $rollbackErrors[] = 'recovery: ' . $restoreError->getMessage();
            }

            if ($rollbackErrors !== []) {
                if ($context->lifecycle->canTransitionTo(ExtensionState::Failed)) {
                    $context->lifecycle->transitionTo(ExtensionState::Failed, 'rollback incomplete');
                }
                $this->safeMode->enable(
                    'Extension update rollback was incomplete.',
                    $context->extensionId,
                    $point->id,
                );
                return new UpdateTransactionResult(
                    false,
                    $context->lifecycle->state(),
                    $point,
                    $error->getMessage(),
                    $rollbackErrors,
                    true,
                );
            }

            $target = $initial;
            if ($context->lifecycle->canTransitionTo($target)) {
                $context->lifecycle->transitionTo($target, 'previous state restored');
            }

            return new UpdateTransactionResult(
                false,
                $context->lifecycle->state(),
                $point,
                $error->getMessage(),
            );
        }
    }
}
