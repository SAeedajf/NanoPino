<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Manifest;

use App\com_pinoox_cms\Cms\Contracts\ExtensionManifestInterface;
use App\com_pinoox_cms\Cms\Extension\ExtensionType;

final readonly class ExtensionManifest implements ExtensionManifestInterface
{
    /**
     * @param list<ExtensionDependencyRule> $dependencies
     * @param list<ExtensionDependencyRule> $optionalDependencies
     * @param array<string,string> $conflicts
     * @param list<string> $provides
     * @param list<string> $replaces
     * @param list<string> $permissions
     * @param list<string> $services
     * @param list<string> $capabilities
     * @param list<string> $abilities
     * @param list<string> $hooks
     * @param array<string,mixed> $admin
     * @param array<string,mixed> $api
     * @param array<string,mixed> $frontend
     * @param array<string,mixed> $pinx
     * @param array<string,mixed> $rawCms
     */
    public function __construct(
        private int $schemaVersion,
        private ExtensionIdentity $identity,
        private string $name,
        private ExtensionType $extensionType,
        private string $version,
        private int $versionCode,
        private string $publisher,
        private ExtensionRequirementSet $requirements,
        private array $dependencies,
        private array $optionalDependencies,
        private array $conflicts,
        private array $provides,
        private array $replaces,
        private array $permissions,
        private array $services,
        private array $capabilities,
        private array $abilities,
        private array $hooks,
        private array $admin,
        private array $api,
        private array $frontend,
        private array $pinx,
        private array $rawCms,
    ) {
    }

    public function schemaVersion(): int { return $this->schemaVersion; }
    public function identifier(): string { return $this->identity->identifier; }
    public function name(): string { return $this->name; }
    public function owner(): string { return $this->identity->owner; }
    public function package(): string { return $this->identity->package; }
    public function targetApp(): ?string { return $this->identity->targetApp; }
    public function themeName(): ?string { return $this->identity->themeName; }
    public function extensionType(): ExtensionType { return $this->extensionType; }
    public function version(): string { return $this->version; }
    public function versionCode(): int { return $this->versionCode; }
    public function publisher(): string { return $this->publisher; }
    public function requirements(): ExtensionRequirementSet { return $this->requirements; }

    /** @return list<ExtensionDependencyRule> */
    public function dependencies(): array { return $this->dependencies; }
    /** @return list<ExtensionDependencyRule> */
    public function optionalDependencies(): array { return $this->optionalDependencies; }
    /** @return array<string,string> */
    public function conflicts(): array { return $this->conflicts; }
    /** @return list<string> */
    public function provides(): array { return $this->provides; }
    /** @return list<string> */
    public function replaces(): array { return $this->replaces; }
    /** @return list<string> */
    public function permissions(): array { return $this->permissions; }
    /** @return list<string> */
    public function services(): array { return $this->services; }
    /** @return list<string> */
    public function capabilities(): array { return $this->capabilities; }
    /** @return list<string> */
    public function abilities(): array { return $this->abilities; }
    /** @return list<string> */
    public function hooks(): array { return $this->hooks; }
    /** @return array<string,mixed> */
    public function admin(): array { return $this->admin; }
    /** @return array<string,mixed> */
    public function api(): array { return $this->api; }
    /** @return array<string,mixed> */
    public function frontend(): array { return $this->frontend; }
    /** @return array<string,mixed> */
    public function pinx(): array { return $this->pinx; }
    /** @return array<string,mixed> */
    public function rawCms(): array { return $this->rawCms; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => $this->schemaVersion,
            'identifier' => $this->identifier(),
            'name' => $this->name(),
            'owner' => $this->owner(),
            'package' => $this->package(),
            'target_app' => $this->targetApp(),
            'theme_name' => $this->themeName(),
            'extension_type' => $this->extensionType->value,
            'version' => $this->version,
            'version_code' => $this->versionCode,
            'publisher' => $this->publisher,
            'requires' => $this->requirements->all(),
            'dependencies' => array_map(static fn (ExtensionDependencyRule $rule) => $rule->toArray(), $this->dependencies),
            'optional_dependencies' => array_map(static fn (ExtensionDependencyRule $rule) => $rule->toArray(), $this->optionalDependencies),
            'conflicts' => $this->conflicts,
            'provides' => $this->provides,
            'replaces' => $this->replaces,
            'permissions' => $this->permissions,
            'services' => $this->services,
            'capabilities' => $this->capabilities,
            'abilities' => $this->abilities,
            'hooks' => $this->hooks,
            'admin' => $this->admin,
            'api' => $this->api,
            'frontend' => $this->frontend,
        ];
    }
}
