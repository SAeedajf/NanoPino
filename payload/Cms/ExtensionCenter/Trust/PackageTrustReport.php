<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Trust;

final readonly class PackageTrustReport
{
    /** @param list<string> $warnings */
    public function __construct(
        public PackageTrustLevel $level,
        public bool $integrityVerified,
        public bool $authenticityVerified,
        public ?string $publisher,
        public ?string $keyId,
        public array $warnings = [],
    ) {}

    public function canProceed(bool $requireSignature = false): bool
    {
        if ($this->level === PackageTrustLevel::Failed || !$this->integrityVerified) {
            return false;
        }

        return !$requireSignature || $this->authenticityVerified;
    }

    public function assuranceLabel(): string
    {
        return match ($this->level) {
            PackageTrustLevel::Verified => 'Publisher signature and package integrity verified.',
            PackageTrustLevel::IntegrityOnly => 'Package integrity verified; publisher signature not verified.',
            PackageTrustLevel::Unverified => 'Package authenticity/integrity is not verified.',
            PackageTrustLevel::Failed => 'Package trust verification failed.',
        };
    }
}
