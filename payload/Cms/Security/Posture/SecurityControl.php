<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Posture;

final readonly class SecurityControl
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $id,
        public string $label,
        public SecurityControlStatus $status,
        public string $message,
        public array $metadata = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'=>$this->id,
            'label'=>$this->label,
            'status'=>$this->status->value,
            'message'=>$this->message,
            'metadata'=>$this->metadata,
        ];
    }
}
