<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

final readonly class AbilityExecutionResult
{
    public function __construct(
        public bool $success,
        public string $ability,
        public string $correlationId,
        public mixed $data = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $replayed = false,
    ) {}

    public function asReplay(): self
    {
        return new self(
            $this->success,
            $this->ability,
            $this->correlationId,
            $this->data,
            $this->errorCode,
            $this->errorMessage,
            true,
        );
    }
}
