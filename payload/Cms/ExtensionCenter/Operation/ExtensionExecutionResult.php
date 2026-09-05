<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

final readonly class ExtensionExecutionResult
{
    public function __construct(
        public bool $success,
        public ?string $recoveryPointId = null,
        public bool $recoveryRequired = false,
        public ?string $error = null,
    ) {}
}
