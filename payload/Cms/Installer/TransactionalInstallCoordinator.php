<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

use App\com_pinoox_cms\Cms\Lifecycle\ExtensionState;
use App\com_pinoox_cms\Cms\Recovery\FaultInjection\FaultInjectorInterface;
use App\com_pinoox_cms\Cms\Recovery\FaultInjection\NullFaultInjector;
use Throwable;

final class TransactionalInstallCoordinator
{
    private readonly FaultInjectorInterface $faults;

    public function __construct(?FaultInjectorInterface $faults = null)
    {
        $this->faults = $faults ?? new NullFaultInjector();
    }

    /** @param list<InstallStepInterface> $steps */
    public function run(InstallContext $context, array $steps): InstallTransactionResult
    {
        $journal = new InstallTransactionJournal();
        $completed = [];

        try {
            $this->assertStepOrder($steps);
            if ($context->lifecycle->state() !== ExtensionState::Validated) {
                throw new \LogicException('Transactional install requires lifecycle state validated.');
            }
            $context->lifecycle->transitionTo(ExtensionState::Installing);

            foreach ($steps as $step) {
                $this->faults->checkpoint('install.step.' . $step->id() . '.before');
                $journal->record($step->id(), $step->phase(), 'started');
                // Add before execute: rollback() must be partial-execution safe.
                $completed[] = $step;
                $step->execute($context);
                $this->faults->checkpoint('install.step.' . $step->id() . '.after');
                $journal->record($step->id(), $step->phase(), 'ok');
            }

            $this->faults->checkpoint('install.commit.before');
            $context->lifecycle->transitionTo(ExtensionState::Installed);
            $journal->record('transaction', InstallPhase::Commit, 'ok');
            return new InstallTransactionResult(true, $context->lifecycle->state(), $journal);
        } catch (Throwable $error) {
            $journal->record('transaction', InstallPhase::Rollback, 'failed', $error->getMessage());

            if ($context->lifecycle->canTransitionTo(ExtensionState::Failed)) {
                $context->lifecycle->transitionTo(ExtensionState::Failed, $error->getMessage());
            }
            if ($context->lifecycle->canTransitionTo(ExtensionState::Rollback)) {
                $context->lifecycle->transitionTo(ExtensionState::Rollback, 'transaction rollback');
            }

            $rollbackErrors = [];
            foreach (array_reverse($completed) as $step) {
                try {
                    $this->faults->checkpoint('install.rollback.' . $step->id() . '.before');
                    $step->rollback($context);
                    $journal->record($step->id(), InstallPhase::Rollback, 'rolled_back');
                } catch (Throwable $rollbackError) {
                    $rollbackErrors[] = $step->id() . ': ' . $rollbackError->getMessage();
                    $journal->record($step->id(), InstallPhase::Rollback, 'rollback_failed', $rollbackError->getMessage());
                }
            }

            $target = $rollbackErrors === [] ? ExtensionState::Removed : ExtensionState::Failed;
            if ($context->lifecycle->canTransitionTo($target)) {
                $context->lifecycle->transitionTo($target, $rollbackErrors === [] ? 'rollback complete' : 'rollback incomplete');
            }

            return new InstallTransactionResult(
                false,
                $context->lifecycle->state(),
                $journal,
                $error->getMessage(),
                $rollbackErrors,
            );
        }
    }

    /** @param list<InstallStepInterface> $steps */
    private function assertStepOrder(array $steps): void
    {
        $rank = [
            InstallPhase::Snapshot->value => 10,
            InstallPhase::Stage->value => 20,
            InstallPhase::Migrate->value => 30,
            InstallPhase::Lifecycle->value => 40,
            InstallPhase::Cache->value => 50,
            InstallPhase::Health->value => 60,
            InstallPhase::Switch->value => 70,
            InstallPhase::Verify->value => 80,
            InstallPhase::Commit->value => 90,
        ];
        $previous = 0;
        foreach ($steps as $step) {
            $current = $rank[$step->phase()->value] ?? 0;
            if ($current === 0 || $current < $previous) {
                throw new \LogicException('Install steps are not in canonical transaction order at: ' . $step->id());
            }
            $previous = $current;
        }
    }
}
