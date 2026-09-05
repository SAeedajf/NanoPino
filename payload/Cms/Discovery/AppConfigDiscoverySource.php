<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

use Closure;

/**
 * Adapter boundary for Pinoox AppEngine/AppManifest integration.
 * The provider returns already-loaded app configs; this class never includes app.php itself.
 */
final class AppConfigDiscoverySource implements ExtensionDiscoverySourceInterface
{
    private Closure $provider;

    /** @param callable():iterable<array<string,mixed>> $provider */
    public function __construct(
        callable $provider,
        private readonly string $sourceName = 'pinoox-apps',
        private readonly AppConfigManifestProjector $projector = new AppConfigManifestProjector(),
    ) {
        $this->provider = Closure::fromCallable($provider);
    }

    public function manifests(): iterable
    {
        $provider = $this->provider;
        foreach ($provider() as $config) {
            $projected = $this->projector->project($config);
            if ($projected !== null) {
                yield $projected;
            }
        }
    }

    public function name(): string
    {
        return $this->sourceName;
    }
}
