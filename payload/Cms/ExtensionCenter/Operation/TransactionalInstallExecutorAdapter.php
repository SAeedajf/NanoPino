<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

use App\com_pinoox_cms\Cms\Installer\InstallContext;
use App\com_pinoox_cms\Cms\Installer\InstallStepInterface;
use App\com_pinoox_cms\Cms\Installer\TransactionalInstallCoordinator;

final readonly class TransactionalInstallExecutorAdapter
{
    /**
     * @param callable(ExtensionOperationRequest):InstallContext $contextFactory
     * @param callable(ExtensionOperationRequest):list<InstallStepInterface> $stepsFactory
     */
    public function __construct(
        private TransactionalInstallCoordinator $coordinator,
        private mixed $contextFactory,
        private mixed $stepsFactory,
    ) {}

    /** @param callable(string,string,string):void $progress */
    public function execute(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        if ($request->type !== ExtensionOperationType::Install) {
            throw new \LogicException('Transactional install adapter accepts install operations only.');
        }
        if (!is_callable($this->contextFactory) || !is_callable($this->stepsFactory)) {
            throw new \LogicException('Transactional install adapter is not configured.');
        }

        $context = ($this->contextFactory)($request);
        $steps = ($this->stepsFactory)($request);

        $progress('transaction', 'started', 'Transactional install started.');
        $result = $this->coordinator->run($context, $steps);

        foreach ($result->journal->entries() as $entry) {
            $progress(
                $entry->step,
                $entry->status,
                $entry->message ?? ('Install phase: ' . $entry->phase->value),
            );
        }

        return new ExtensionExecutionResult(
            $result->success,
            null,
            !$result->success && $result->rollbackErrors !== [],
            $result->error,
        );
    }
}
