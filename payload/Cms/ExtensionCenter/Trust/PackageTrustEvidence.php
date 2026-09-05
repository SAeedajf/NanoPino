<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Trust;

final readonly class PackageTrustEvidence
{
    public function __construct(
        public bool $checksumVerified,
        public bool $signaturePresent,
        public bool $signatureVerified,
        public ?string $verifiedPublisher = null,
        public ?string $keyId = null,
        public ?string $source = null,
    ) {}
}
