<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

use App\com_pinoox_cms\Cms\Exception\ManifestValidationException;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifestFactory;

final readonly class ExtensionDiscoveryService
{
    public function __construct(private ExtensionManifestFactory $factory = new ExtensionManifestFactory())
    {
    }

    /** @param iterable<ExtensionDiscoverySourceInterface> $sources */
    public function discover(iterable $sources): DiscoveryResult
    {
        $extensions = [];
        $problems = [];
        $seen = [];

        foreach ($sources as $source) {
            $index = 0;
            foreach ($source->manifests() as $raw) {
                ++$index;
                try {
                    $manifest = $this->factory->fromPinxArray($raw);
                    $identifier = $manifest->identifier();
                    if (isset($seen[$identifier])) {
                        $problems[] = new DiscoveryProblem(
                            $source->name(),
                            $index,
                            ['Duplicate extension identity discovered: ' . $identifier],
                        );
                        continue;
                    }
                    $seen[$identifier] = true;
                    $extensions[] = new DiscoveredExtension($manifest, $source->name());
                } catch (ManifestValidationException $e) {
                    $problems[] = new DiscoveryProblem($source->name(), $index, $e->violations());
                }
            }
        }

        return new DiscoveryResult($extensions, $problems);
    }
}
