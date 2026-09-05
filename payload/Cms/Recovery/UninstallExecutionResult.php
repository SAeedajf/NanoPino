<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final readonly class UninstallExecutionResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
    ) {}
}
