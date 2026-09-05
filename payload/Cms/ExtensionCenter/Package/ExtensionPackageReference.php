<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Package;

final readonly class ExtensionPackageReference
{
    public function __construct(
        public string $localPath,
        public string $displayName,
        public int $size,
    ) {}
}
