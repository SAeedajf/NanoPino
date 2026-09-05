<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

final readonly class ExtensionUpdatePolicy
{
    public function __construct(
        public string $extensionId,
        public UpdateChannel $channel = UpdateChannel::Stable,
        public AutoUpdateMode $autoUpdate = AutoUpdateMode::Disabled,
        public bool $requireSignature = false,
        public bool $allowDowngrade = false,
        public bool $snapshotBeforeUpdate = true,
        public bool $healthCheckRequired = true,
    ) {
        if ($extensionId === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,189}$/', $extensionId) !== 1) {
            throw new \InvalidArgumentException('Invalid update policy extension id.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'extension_id' => $this->extensionId,
            'channel' => $this->channel->value,
            'auto_update' => $this->autoUpdate->value,
            'require_signature' => $this->requireSignature,
            'allow_downgrade' => $this->allowDowngrade,
            'snapshot_before_update' => $this->snapshotBeforeUpdate,
            'health_check_required' => $this->healthCheckRequired,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['extension_id'] ?? ''),
            UpdateChannel::from((string)($data['channel'] ?? 'stable')),
            AutoUpdateMode::from((string)($data['auto_update'] ?? 'disabled')),
            (bool)($data['require_signature'] ?? false),
            (bool)($data['allow_downgrade'] ?? false),
            (bool)($data['snapshot_before_update'] ?? true),
            (bool)($data['health_check_required'] ?? true),
        );
    }
}
