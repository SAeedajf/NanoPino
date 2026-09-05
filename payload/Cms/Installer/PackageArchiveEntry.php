<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

final readonly class PackageArchiveEntry
{
    public function __construct(
        public string $path,
        public PackageEntryType $type,
        public int $size = 0,
        public ?string $sha256 = null,
        public ?int $compressedSize = null,
    ) {
        if ($compressedSize !== null && $compressedSize < 0) {
            throw new \InvalidArgumentException('Compressed package entry size cannot be negative.');
        }
    }
}
