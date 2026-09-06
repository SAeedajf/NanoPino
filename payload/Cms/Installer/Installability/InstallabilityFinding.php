<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer\Installability;

final readonly class InstallabilityFinding
{
    /** @param array<string,mixed> $details */
    public function __construct(
        public string $code,
        public InstallabilitySeverity $severity,
        public string $message,
        public array $details = [],
    ) {}

    /** @return array{code:string,severity:string,message:string,details:array<string,mixed>} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
