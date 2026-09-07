<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Extension;

use App\com_pinoox_cms\Cms\Contracts\ExtensionDefinitionInterface;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;
use InvalidArgumentException;

final readonly class ExtensionDefinition implements ExtensionDefinitionInterface
{
    private string $identifier;
    private string $owner;

    public function __construct(
        private string $package,
        private string $name,
        private ExtensionType $type,
        private string $version,
        private string $publisher,
        ?string $identifier = null,
        ?string $owner = null,
    ) {
        $this->identifier = $identifier ?? $package;
        $this->owner = $owner ?? $package;

        if ($this->identifier === '' || $this->owner === '' || $package === '') {
            throw new InvalidArgumentException('Extension identity fields cannot be empty.');
        }
        if ($version === '') {
            throw new InvalidArgumentException('Extension version cannot be empty.');
        }
        if ($publisher === '') {
            throw new InvalidArgumentException('Extension publisher cannot be empty.');
        }
    }

    public static function fromManifest(ExtensionManifest $manifest): self
    {
        return new self(
            package: $manifest->package(),
            name: $manifest->name(),
            type: $manifest->extensionType(),
            version: $manifest->version(),
            publisher: $manifest->publisher(),
            identifier: $manifest->identifier(),
            owner: $manifest->owner(),
        );
    }

    public function identifier(): string { return $this->identifier; }
    public function owner(): string { return $this->owner; }
    public function package(): string { return $this->package; }
    public function name(): string { return $this->name; }
    public function type(): ExtensionType { return $this->type; }
    public function version(): string { return $this->version; }
    public function publisher(): string { return $this->publisher; }
}
