<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Grant;

final readonly class ExtensionPermissionGrant
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public string $extensionId,
        public string $version,
        public int $versionCode,
        public string $packageSha256,
        public array $permissions,
        public ?int $approvedBy,
        public float $approvedAt,
    ) {
        if ($extensionId === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,189}$/', $extensionId) !== 1) {
            throw new \InvalidArgumentException('Invalid extension grant id.');
        }
        if ($versionCode < 0 || preg_match('/^[a-f0-9]{64}$/', $packageSha256) !== 1) {
            throw new \InvalidArgumentException('Invalid extension grant package identity.');
        }
        foreach ($permissions as $permission) {
            if (!is_string($permission) || $permission === '') {
                throw new \InvalidArgumentException('Invalid extension permission grant.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'extension_id' => $this->extensionId,
            'version' => $this->version,
            'version_code' => $this->versionCode,
            'package_sha256' => $this->packageSha256,
            'permissions' => array_values(array_unique($this->permissions)),
            'approved_by' => $this->approvedBy,
            'approved_at' => $this->approvedAt,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['extension_id'] ?? ''),
            (string)($data['version'] ?? ''),
            (int)($data['version_code'] ?? 0),
            (string)($data['package_sha256'] ?? ''),
            array_values(array_filter(
                is_array($data['permissions'] ?? null) ? $data['permissions'] : [],
                'is_string'
            )),
            isset($data['approved_by']) ? (int)$data['approved_by'] : null,
            (float)($data['approved_at'] ?? 0),
        );
    }
}
