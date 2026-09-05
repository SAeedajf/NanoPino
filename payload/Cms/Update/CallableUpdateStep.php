<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Update;

final class CallableUpdateStep implements UpdateStepInterface
{
    public function __construct(
        private readonly string $stepId,
        private readonly \Closure $executeCallback,
        private readonly ?\Closure $rollbackCallback = null,
    ) {}

    public function id(): string { return $this->stepId; }

    public function execute(UpdateContext $context): void
    {
        ($this->executeCallback)($context);
    }

    public function rollback(UpdateContext $context): void
    {
        if ($this->rollbackCallback !== null) {
            ($this->rollbackCallback)($context);
        }
    }
}
