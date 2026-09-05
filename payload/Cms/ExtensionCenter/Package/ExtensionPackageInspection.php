<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Package;

use App\com_pinoox_cms\Cms\ExtensionCenter\Trust\PackageTrustReport;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;
use App\com_pinoox_cms\Cms\Security\Package\PackagePreflightReport;

final readonly class ExtensionPackageInspection
{
    /**
     * Local filesystem paths are deliberately not serialized as public metadata.
     */
    public function __construct(
        public ExtensionManifest $manifest,
        public PackageTrustReport $trust,
        public string $displayName,
        public int $size,
        public string $packageSha256,
        public ?PackagePreflightReport $security = null,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $packageSha256) !== 1) {
            throw new \InvalidArgumentException('Invalid package SHA-256.');
        }
    }

    /** @return array<string,mixed> */
    public function publicData(): array
    {
        return [
            'name' => $this->displayName,
            'size' => $this->size,
            'sha256' => $this->packageSha256,
            'manifest' => $this->manifest->toArray(),
            'security' => $this->security?->publicData(),
            'trust' => [
                'level' => $this->trust->level->value,
                'integrity_verified' => $this->trust->integrityVerified,
                'authenticity_verified' => $this->trust->authenticityVerified,
                'publisher' => $this->trust->publisher,
                'key_id' => $this->trust->keyId,
                'warnings' => $this->trust->warnings,
                'assurance' => $this->trust->assuranceLabel(),
            ],
        ];
    }
}
