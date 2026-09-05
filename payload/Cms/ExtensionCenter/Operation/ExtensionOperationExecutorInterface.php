<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

interface ExtensionOperationExecutorInterface
{
    /**
     * Production adapter MUST delegate to the existing transactional
     * install/update/recovery coordinators and native PINX primitives.
     *
     * @param callable(string,string,string):void $progress
     */
    public function execute(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult;
}
