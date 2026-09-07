<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Manifest;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;

final readonly class ExtensionManifestFactory
{
    public function __construct(private ExtensionManifestValidator $validator = new ExtensionManifestValidator())
    {
    }

    /** @param array<string,mixed> $pinx */
    public function fromPinxArray(array $pinx): ExtensionManifest
    {
        $this->validator->validate($pinx);

        /** @var array<string,mixed> $cms */
        $cms = $pinx['cms'];
        $type = ExtensionType::from((string) $cms['extension_type']);
        $identity = ExtensionIdentity::fromPinx($pinx, $type);

        $dependencies = ManifestNormalizer::dependencies($cms['dependencies'] ?? []);
        $optionalDependencies = ManifestNormalizer::dependencies($cms['optional_dependencies'] ?? [], true);

        // Rules declared optional inside dependencies are normalized into the optional bucket.
        foreach ($dependencies as $index => $rule) {
            if ($rule->optional) {
                $optionalDependencies[] = $rule;
                unset($dependencies[$index]);
            }
        }

        return new ExtensionManifest(
            schemaVersion: (int) $cms['schema'],
            identity: $identity,
            name: trim((string)($pinx['name'] ?? '')) !== ''
                ? trim((string)$pinx['name'])
                : $identity->package,
            extensionType: $type,
            version: (string) $pinx['version_name'],
            versionCode: (int) $pinx['version_code'],
            publisher: (string) $cms['publisher'],
            requirements: new ExtensionRequirementSet(ManifestNormalizer::stringMap($cms['requires'] ?? [])),
            dependencies: array_values($dependencies),
            optionalDependencies: array_values($optionalDependencies),
            conflicts: ManifestNormalizer::stringMap($cms['conflicts'] ?? []),
            provides: ManifestNormalizer::stringList($cms['provides'] ?? []),
            replaces: ManifestNormalizer::stringList($cms['replaces'] ?? []),
            permissions: ManifestNormalizer::stringList($cms['permissions'] ?? []),
            services: ManifestNormalizer::stringList($cms['services'] ?? []),
            capabilities: ManifestNormalizer::stringList($cms['capabilities'] ?? []),
            abilities: ManifestNormalizer::stringList($cms['abilities'] ?? []),
            hooks: ManifestNormalizer::stringList($cms['hooks'] ?? []),
            admin: is_array($cms['admin'] ?? null) ? $cms['admin'] : [],
            api: is_array($cms['api'] ?? null) ? $cms['api'] : [],
            frontend: is_array($cms['frontend'] ?? null) ? $cms['frontend'] : [],
            pinx: $pinx,
            rawCms: $cms,
        );
    }
}
