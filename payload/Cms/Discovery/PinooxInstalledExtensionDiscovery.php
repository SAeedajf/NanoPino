<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

use App\com_pinoox_cms\Cms\Dependency\ExtensionCatalog;
use App\com_pinoox_cms\Cms\Dependency\InstalledExtension;
use App\com_pinoox_cms\Cms\Extension\ExtensionDefinition;
use App\com_pinoox_cms\Cms\Extension\ExtensionRegistry;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifestFactory;
use Pinoox\Portal\App\AppEngine;

final class PinooxInstalledExtensionDiscovery
{
    /** @return list<ExtensionManifest> */
    public function manifests(): array
    {
        $items = [];
        $factory = new ExtensionManifestFactory();
        $projector = new AppConfigManifestProjector();

        foreach (array_keys(AppEngine::packagePaths()) as $package) {
            try {
                $config = AppEngine::config($package)->get();
                if (!is_array($config)) continue;
                $raw = $projector->project($config);
                if ($raw === null) continue;
                $items[] = $factory->fromPinxArray($raw);
            } catch (\Throwable) {
                // Malformed third-party manifests must not break CMS Admin boot.
            }
        }

        usort($items, static fn(ExtensionManifest $a, ExtensionManifest $b): int =>
            strcmp($a->identifier(), $b->identifier())
        );
        return $items;
    }

    public function syncRegistry(ExtensionRegistry $registry): int
    {
        $count = 0;
        foreach ($this->manifests() as $manifest) {
            $definition = ExtensionDefinition::fromManifest($manifest);
            if ($registry->extension($definition->identifier()) !== null) continue;
            $registry->register($definition);
            $count++;
        }
        return $count;
    }

    public function catalog(): ExtensionCatalog
    {
        $catalog = new ExtensionCatalog();
        foreach ($this->manifests() as $manifest) {
            $active = true;
            try { $active = AppEngine::stable($manifest->package()); } catch (\Throwable) {}
            $catalog->add(InstalledExtension::fromManifest($manifest, $active));
        }
        return $catalog;
    }
}
