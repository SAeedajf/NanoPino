<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

use App\com_pinoox_cms\Cms\Update\TransactionalUpdateCoordinator;
use App\com_pinoox_cms\Cms\Update\UpdateContext;
use App\com_pinoox_cms\Cms\Update\UpdateStepInterface;

final readonly class TransactionalUpdateExecutorAdapter
{
    /**
     * @param callable(ExtensionOperationRequest):UpdateContext $contextFactory
     * @param callable(ExtensionOperationRequest):list<UpdateStepInterface> $stepsFactory
     */
    public function __construct(
        private TransactionalUpdateCoordinator $coordinator,
        private mixed $contextFactory,
        private mixed $stepsFactory,
    ) {}

    /** @param callable(string,string,string):void $progress */
    public function execute(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        if ($request->type !== ExtensionOperationType::Update) {
            throw new \LogicException('Transactional update adapter accepts update operations only.');
        }
        if (!is_callable($this->contextFactory) || !is_callable($this->stepsFactory)) {
            throw new \LogicException('Transactional update adapter is not configured.');
        }

        $context = ($this->contextFactory)($request);
        $steps = ($this->stepsFactory)($request);

        $progress('snapshot', 'started', 'Creating recovery point and starting transactional update.');
        $result = $this->coordinator->run($context, $steps);

        $progress(
            'recovery',
            $result->success ? 'ready' : ($result->safeModeEnabled ? 'required' : 'available'),
            'Recovery point: ' . $result->recoveryPoint->id,
        );

        return new ExtensionExecutionResult(
            $result->success,
            $result->recoveryPoint->id,
            $result->safeModeEnabled || $result->rollbackErrors !== [],
            $result->error,
        );
    }
}
