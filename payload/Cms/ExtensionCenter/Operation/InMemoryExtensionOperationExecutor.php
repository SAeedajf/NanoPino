<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

final readonly class InMemoryExtensionOperationExecutor implements ExtensionOperationExecutorInterface
{
    /** @param callable(ExtensionOperationRequest,callable):ExtensionExecutionResult $handler */
    public function __construct(private mixed $handler) {}

    public function execute(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        if (!is_callable($this->handler)) {
            throw new \LogicException('In-memory extension executor handler is not callable.');
        }

        return ($this->handler)($request, $progress);
    }
}
