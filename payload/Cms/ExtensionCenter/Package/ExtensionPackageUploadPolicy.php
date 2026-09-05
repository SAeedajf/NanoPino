<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Package;

use InvalidArgumentException;

final readonly class ExtensionPackageUploadPolicy
{
    public function __construct(
        private int $maxBytes = 104_857_600,
    ) {
        if ($maxBytes < 1 || $maxBytes > 1_073_741_824) {
            throw new InvalidArgumentException('Invalid extension package size limit.');
        }
    }

    public function validate(string $localPath, string $displayName, int $size): ExtensionPackageReference
    {
        if (
            $size < 1
            || $size > $this->maxBytes
            || str_contains($displayName, "\0")
            || strlen($displayName) > 255
        ) {
            throw new InvalidArgumentException('Invalid extension package upload.');
        }

        $name = basename(str_replace('\\', '/', $displayName));
        if ($name !== $displayName && basename($displayName) !== $displayName) {
            throw new InvalidArgumentException('Extension upload display name may not contain a path.');
        }

        if (!preg_match('/\.(?:pinx|zip)$/i', $name)) {
            throw new InvalidArgumentException('Extension package must use .pinx or .zip transport.');
        }

        if (
            trim($localPath) === ''
            || str_contains($localPath, "\0")
            || !is_file($localPath)
            || is_link($localPath)
        ) {
            throw new InvalidArgumentException('Extension package temporary file is invalid.');
        }

        $actual = filesize($localPath);
        if ($actual === false || $actual !== $size) {
            throw new InvalidArgumentException('Extension package size does not match the upload metadata.');
        }

        return new ExtensionPackageReference($localPath, $name, $size);
    }
}
