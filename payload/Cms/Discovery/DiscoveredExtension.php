<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;

final readonly class DiscoveredExtension
{
    public function __construct(
        public ExtensionManifest $manifest,
        public string $source,
    ) {
    }
}
