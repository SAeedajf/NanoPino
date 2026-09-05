<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

use Closure;

final class CallableInstallStep implements InstallStepInterface
{
    private Closure $executeCallback;
    private Closure $rollbackCallback;

    public function __construct(
        private readonly string $stepId,
        private readonly InstallPhase $installPhase,
        callable $execute,
        ?callable $rollback = null,
    ) {
        $this->executeCallback = Closure::fromCallable($execute);
        $this->rollbackCallback = $rollback !== null
            ? Closure::fromCallable($rollback)
            : static function (InstallContext $context): void {};
    }

    public function id(): string { return $this->stepId; }
    public function phase(): InstallPhase { return $this->installPhase; }
    public function execute(InstallContext $context): void { ($this->executeCallback)($context); }
    public function rollback(InstallContext $context): void { ($this->rollbackCallback)($context); }
}
