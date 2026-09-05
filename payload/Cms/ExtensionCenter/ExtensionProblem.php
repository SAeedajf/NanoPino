<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

final readonly class ExtensionProblem
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $code,
        public ExtensionProblemSeverity $severity,
        public string $message,
        public bool $blocking = false,
        public array $metadata = [],
    ) {}
}
