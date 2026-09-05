<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

interface UninstallExecutorInterface
{
    /**
     * Production adapter delegates to Pincore PinxUninstaller.
     *
     * @param callable(string,string,string):void $progress
     */
    public function uninstall(string $extensionId, callable $progress): UninstallExecutionResult;
}
