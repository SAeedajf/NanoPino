<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Package;

use App\com_pinoox_cms\Cms\Installer\CanonicalPackagePath;
use App\com_pinoox_cms\Cms\Installer\PackagePayloadReaderInterface;
use PhpZip\ZipFile;
use Pinoox\Component\Package\Pinx\PinxManifest;

final readonly class PinxPayloadReader implements PackagePayloadReaderInterface
{
    public function __construct(private ZipFile $zip) {}

    public function read(string $canonicalPath): string
    {
        $path = CanonicalPackagePath::normalize($canonicalPath);
        $entry = PinxManifest::PAYLOAD_PREFIX . $path;
        if (!$this->zip->hasEntry($entry) || $this->zip->isDirectory($entry)) {
            throw new \RuntimeException('PINX payload entry is missing: ' . $path);
        }

        return $this->zip->getEntryContents($entry);
    }
}
