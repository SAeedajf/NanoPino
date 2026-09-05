<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Trust;

final class PackageTrustEvaluator
{
    public function evaluate(PackageTrustEvidence $evidence): PackageTrustReport
    {
        if (!$evidence->checksumVerified) {
            return new PackageTrustReport(
                PackageTrustLevel::Failed,
                false,
                false,
                null,
                null,
                ['Package checksum/integrity verification failed.'],
            );
        }

        if ($evidence->signaturePresent && !$evidence->signatureVerified) {
            return new PackageTrustReport(
                PackageTrustLevel::Failed,
                true,
                false,
                null,
                $evidence->keyId,
                ['A package signature was present but did not verify.'],
            );
        }

        if ($evidence->signatureVerified) {
            return new PackageTrustReport(
                PackageTrustLevel::Verified,
                true,
                true,
                $evidence->verifiedPublisher,
                $evidence->keyId,
                [
                    'Cryptographic verification does not mean the extension code is safe or vulnerability-free.',
                ],
            );
        }

        return new PackageTrustReport(
            PackageTrustLevel::IntegrityOnly,
            true,
            false,
            null,
            null,
            [
                'Package is unsigned or its publisher identity is not cryptographically verified.',
                'Integrity verification does not mean the extension code is safe or vulnerability-free.',
            ],
        );
    }
}
