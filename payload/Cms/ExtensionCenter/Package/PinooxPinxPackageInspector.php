<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Package;

use App\com_pinoox_cms\Cms\ExtensionCenter\Trust\PackageTrustEvaluator;
use App\com_pinoox_cms\Cms\ExtensionCenter\Trust\PackageTrustEvidence;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifestFactory;
use App\com_pinoox_cms\Cms\Security\Package\PinooxPinxPackagePreflight;
use Pinoox\Component\Package\Pinx\PinxReader;
use Pinoox\Component\Package\Pinx\PinxSignature;
use Pinoox\Component\Package\Pinx\PinxVerifier;

final readonly class PinooxPinxPackageInspector implements ExtensionPackageInspectorInterface
{
    public function __construct(
        private ExtensionManifestFactory $factory = new ExtensionManifestFactory(),
        private PackageTrustEvaluator $trust = new PackageTrustEvaluator(),
        private PinooxPinxPackagePreflight $preflight = new PinooxPinxPackagePreflight(),
    ) {}

    public function inspect(ExtensionPackageReference $package): ExtensionPackageInspection
    {
        $actualSize = filesize($package->localPath);
        if ($actualSize !== $package->size) {
            throw new \RuntimeException('Extension package changed before inspection.');
        }
        $sha = hash_file('sha256', $package->localPath);
        if (!is_string($sha)) {
            throw new \RuntimeException('Unable to hash extension package.');
        }

        $reader = new PinxReader();
        $reader->open($package->localPath);
        try {
            $native = $reader->manifest();
            $security = $this->preflight->inspectReader($reader);
            $manifest = $this->factory->fromPinxArray($native->toArray());
            $signature = $reader->signature();
            $signaturePresent = $signature !== null;
            $signatureVerified = false;

            if ($signaturePresent) {
                $payloadHashes = PinxSignature::payloadHashes($reader->zip());
                PinxSignature::verify($reader->manifestJson(), $payloadHashes, $signature);
                PinxVerifier::verify(
                    $reader->zip(),
                    $native,
                    $reader->manifestJson(),
                    ['require_signature' => true],
                );
                $signatureVerified = true;
            }

            $trust = $this->trust->evaluate(new PackageTrustEvidence(
                checksumVerified: true,
                signaturePresent: $signaturePresent,
                signatureVerified: $signatureVerified,
                verifiedPublisher: $signatureVerified ? $manifest->publisher() : null,
                keyId: $signatureVerified && is_array($signature)
                    ? ((string)($signature['key_id'] ?? '') ?: null)
                    : null,
                source: 'pinoox-pinx',
            ));

            return new ExtensionPackageInspection(
                $manifest,
                $trust,
                $package->displayName,
                $package->size,
                $sha,
                $security,
            );
        } finally {
            $reader->close();
        }
    }
}
