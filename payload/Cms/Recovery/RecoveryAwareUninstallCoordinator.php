<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use Throwable;

final readonly class RecoveryAwareUninstallCoordinator
{
    public function __construct(
        private RecoveryManager $recovery,
        private SafeModeManager $safeMode,
        private UninstallExecutorInterface $executor,
    ) {}

    /**
     * @param callable(string,string,string):void $progress
     */
    public function run(string $extensionId, callable $progress): UninstallExecutionResult
    {
        $point = $this->recovery->create($extensionId, 'uninstall', [
            'destructive_operation' => true,
        ]);
        $progress('snapshot', 'ok', 'Recovery point created before uninstall.');

        try {
            $result = $this->executor->uninstall($extensionId, $progress);
            if ($result->success) {
                return $result;
            }

            throw new \RuntimeException($result->error ?? 'Uninstall failed.');
        } catch (Throwable $error) {
            try {
                $this->recovery->restore($point->id);
                $progress('rollback', 'ok', 'Uninstall failed; recovery point restored.');
                return new UninstallExecutionResult(false, $error->getMessage());
            } catch (Throwable $restoreError) {
                $this->safeMode->enable(
                    'Uninstall recovery was incomplete.',
                    $extensionId,
                    $point->id,
                );
                $progress('recovery', 'error', 'Uninstall recovery incomplete; Safe Mode enabled.');
                return new UninstallExecutionResult(
                    false,
                    $error->getMessage() . ' | recovery: ' . $restoreError->getMessage(),
                );
            }
        }
    }
}
