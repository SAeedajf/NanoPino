<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

interface ExtensionDiscoverySourceInterface
{
    /** @return iterable<array<string,mixed>> Raw PINX-compatible manifest arrays. */
    public function manifests(): iterable;

    public function name(): string;
}
