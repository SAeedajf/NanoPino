<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

final readonly class ExtensionOperationStep
{
    public function __construct(
        public string $step,
        public string $status,
        public string $message,
        public float $occurredAt,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'status' => $this->status,
            'message' => $this->message,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
