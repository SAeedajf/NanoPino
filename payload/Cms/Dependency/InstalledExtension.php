<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;

final readonly class InstalledExtension
{
    /** @param list<string> $provides @param list<string> $replaces */
    public function __construct(
        public string $identifier,
        public string $package,
        public string $version,
        public int $versionCode,
        public array $provides = [],
        public array $replaces = [],
        public bool $active = true,
    ) {
    }

    public static function fromManifest(ExtensionManifest $manifest, bool $active = true): self
    {
        return new self(
            $manifest->identifier(),
            $manifest->package(),
            $manifest->version(),
            $manifest->versionCode(),
            $manifest->provides(),
            $manifest->replaces(),
            $active,
        );
    }

    public function supplies(string $target): bool
    {
        return $this->identifier === $target
            || $this->package === $target
            || in_array($target, $this->provides, true)
            || in_array($target, $this->replaces, true);
    }
}
