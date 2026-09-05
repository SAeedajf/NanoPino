<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

interface PackagePayloadReaderInterface
{
    public function read(string $canonicalPath): string;
}
