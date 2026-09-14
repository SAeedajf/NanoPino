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
            'schema' => 1,
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
        if (array_key_exists('schema', $data) && (int)$data['schema'] !== 1) {
            throw new \InvalidArgumentException('Unsupported Safe Mode state schema.');
        }
        if (array_key_exists('enabled', $data) && !is_bool($data['enabled']) && !in_array($data['enabled'], [0, 1], true)) {
            throw new \InvalidArgumentException('Invalid Safe Mode enabled flag.');
        }
        if (array_key_exists('reason', $data) && $data['reason'] !== null && !is_string($data['reason'])) {
            throw new \InvalidArgumentException('Invalid Safe Mode reason.');
        }
        if (array_key_exists('enabled_at', $data) && $data['enabled_at'] !== null && !is_numeric($data['enabled_at'])) {
            throw new \InvalidArgumentException('Invalid Safe Mode timestamp.');
        }
        if (array_key_exists('recovery_point_id', $data) && $data['recovery_point_id'] !== null && !is_string($data['recovery_point_id'])) {
            throw new \InvalidArgumentException('Invalid Safe Mode recovery point.');
        }
        if (array_key_exists('quarantined', $data) && !is_array($data['quarantined'])) {
            throw new \InvalidArgumentException('Invalid Safe Mode quarantine list.');
        }

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

    public static function failClosed(string $reason): self
    {
        return new self(true, $reason, microtime(true));
    }
}
