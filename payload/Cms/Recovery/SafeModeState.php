<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final class SafeModeState
{
    /** @param list<string> $quarantined */
    public function __construct(
        public bool $enabled = false,
        public ?string $reason = null,
        public ?float $enabledAt = null,
        public array $quarantined = [],
        public ?string $recoveryPointId = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'reason' => $this->reason,
            'enabled_at' => $this->enabledAt,
            'quarantined' => array_values(array_unique($this->quarantined)),
            'recovery_point_id' => $this->recoveryPointId,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (bool)($data['enabled'] ?? false),
            isset($data['reason']) ? (string)$data['reason'] : null,
            isset($data['enabled_at']) ? (float)$data['enabled_at'] : null,
            array_values(array_filter(
                is_array($data['quarantined'] ?? null) ? $data['quarantined'] : [],
                'is_string'
            )),
            isset($data['recovery_point_id']) ? (string)$data['recovery_point_id'] : null,
        );
    }
}
