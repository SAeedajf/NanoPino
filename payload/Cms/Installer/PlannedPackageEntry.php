<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

final readonly class PlannedPackageEntry
{
    public function __construct(
        public string $path,
        public PackageEntryType $type,
        public int $size,
        public ?string $sha256,
    ) {
    }
}
