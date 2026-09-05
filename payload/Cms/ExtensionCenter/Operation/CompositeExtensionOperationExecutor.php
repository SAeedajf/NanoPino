<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

final readonly class CompositeExtensionOperationExecutor implements ExtensionOperationExecutorInterface
{
    /**
     * Non-install/update mutations are adapters to native PINX/lifecycle/recovery primitives.
     *
     * @param callable(ExtensionOperationRequest,callable):ExtensionExecutionResult|null $lifecycleExecutor
     * @param callable(ExtensionOperationRequest,callable):ExtensionExecutionResult|null $uninstallExecutor
     * @param callable(ExtensionOperationRequest,callable):ExtensionExecutionResult|null $recoveryExecutor
     */
    public function __construct(
        private ?TransactionalInstallExecutorAdapter $install = null,
        private ?TransactionalUpdateExecutorAdapter $update = null,
        private mixed $lifecycleExecutor = null,
        private mixed $uninstallExecutor = null,
        private mixed $recoveryExecutor = null,
    ) {}

    public function execute(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        return match ($request->type) {
            ExtensionOperationType::Install =>
                $this->install?->execute($request, $progress)
                    ?? throw new \LogicException('Transactional install executor is not bound.'),

            ExtensionOperationType::Update =>
                $this->update?->execute($request, $progress)
                    ?? throw new \LogicException('Transactional update executor is not bound.'),

            ExtensionOperationType::Activate,
            ExtensionOperationType::Deactivate,
            ExtensionOperationType::Repair =>
                $this->invoke($this->lifecycleExecutor, $request, $progress, 'lifecycle'),

            ExtensionOperationType::Uninstall =>
                $this->invoke($this->uninstallExecutor, $request, $progress, 'uninstall'),

            ExtensionOperationType::Rollback =>
                $this->invoke($this->recoveryExecutor, $request, $progress, 'recovery'),
        };
    }

    private function invoke(
        mixed $executor,
        ExtensionOperationRequest $request,
        callable $progress,
        string $boundary,
    ): ExtensionExecutionResult {
        if (!is_callable($executor)) {
            throw new \LogicException('Extension ' . $boundary . ' executor is not bound.');
        }

        $result = $executor($request, $progress);
        if (!$result instanceof ExtensionExecutionResult) {
            throw new \LogicException('Extension ' . $boundary . ' executor returned invalid result.');
        }
        return $result;
    }
}
