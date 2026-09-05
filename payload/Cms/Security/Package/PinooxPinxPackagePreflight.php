<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Package;

use App\com_pinoox_cms\Cms\Installer\CanonicalPackagePath;
use App\com_pinoox_cms\Cms\Installer\PackageArchiveEntry;
use App\com_pinoox_cms\Cms\Installer\PackageEntryType;
use App\com_pinoox_cms\Cms\Installer\PackageFilePlan;
use PhpZip\Constants\ZipPlatform;
use PhpZip\Model\ZipEntry;
use Pinoox\Component\Package\Pinx\PinxManifest;
use Pinoox\Component\Package\Pinx\PinxReader;

/**
 * CMS security preflight layered in front of the native Pinoox PINX installer.
 *
 * Pinoox remains the installation/lifecycle owner. This service only validates
 * archive paths/resources and performs static review before native mutation.
 */
final readonly class PinooxPinxPackagePreflight
{
    public function __construct(
        private PackageResourceGuard $resources = new PackageResourceGuard(),
        private ExtensionStaticRiskScanner $scanner = new ExtensionStaticRiskScanner(),
    ) {}

    public function inspectPath(string $path): PackagePreflightReport
    {
        $reader = new PinxReader();
        $reader->open($path);
        try {
            return $this->inspectReader($reader);
        } finally {
            $reader->close();
        }
    }

    public function inspectReader(PinxReader $reader): PackagePreflightReport
    {
        $zip = $reader->zip();
        $archiveEntries = [];
        $payloadEntries = [];
        $prefix = PinxManifest::PAYLOAD_PREFIX;

        foreach ($zip->getListFiles() as $archivePath) {
            // Validate every archive entry, including non-payload metadata. This bounds
            // parser/resource exposure before the native installer is allowed to mutate.
            $canonicalArchivePath = CanonicalPackagePath::normalize($archivePath);
            $entry = $zip->getEntry($archivePath);
            $type = $this->entryType($entry, $zip->isDirectory($archivePath));
            $size = $type === PackageEntryType::Directory ? 0 : $entry->getUncompressedSize();
            $compressed = $type === PackageEntryType::Directory ? 0 : $entry->getCompressedSize();

            // Central-directory sizes are required so resource checks happen before decompression.
            if ($size < 0 || $compressed < 0) {
                throw new \RuntimeException('PINX entry has unknown archive size metadata: ' . $canonicalArchivePath);
            }

            $archiveEntries[] = new PackageArchiveEntry(
                $canonicalArchivePath,
                $type,
                $size,
                null,
                $compressed,
            );

            if (!str_starts_with($canonicalArchivePath, $prefix)) {
                continue;
            }

            $relative = substr($canonicalArchivePath, strlen($prefix));
            if ($relative === '') {
                continue;
            }

            $payloadEntries[] = new PackageArchiveEntry(
                $relative,
                $type,
                $size,
                null,
                $compressed,
            );
        }

        if ($payloadEntries === []) {
            throw new \RuntimeException('PINX package does not contain a payload file plan.');
        }

        // First validate the whole archive for path/symlink/collision/resource abuse, then
        // build the exact payload plan that maps to native PINX extraction targets.
        $archivePlan = new PackageFilePlan($archiveEntries, $this->resources);
        $plan = new PackageFilePlan($payloadEntries, $this->resources);
        $payload = new PinxPayloadReader($zip);
        $findings = $this->scanner->scan($plan, $payload);

        return new PackagePreflightReport($plan, $findings, $archivePlan->count());
    }

    private function entryType(ZipEntry $entry, bool $directory): PackageEntryType
    {
        if ($directory) {
            return PackageEntryType::Directory;
        }

        if ($this->isUnixSymlink($entry)) {
            return PackageEntryType::Symlink;
        }

        return PackageEntryType::File;
    }

    private function isUnixSymlink(ZipEntry $entry): bool
    {
        if (!in_array($entry->getCreatedOS(), [ZipPlatform::OS_UNIX, ZipPlatform::OS_MAC_OSX], true)) {
            return false;
        }

        $mode = ($entry->getExternalAttributes() >> 16) & 0xFFFF;
        return ($mode & 0xF000) === 0xA000;
    }
}
