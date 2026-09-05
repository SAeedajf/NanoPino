<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

final readonly class ArrayManifestDiscoverySource implements ExtensionDiscoverySourceInterface
{
    /** @param list<array<string,mixed>> $items */
    public function __construct(private array $items, private string $sourceName = 'array')
    {
    }

    public function manifests(): iterable
    {
        yield from $this->items;
    }

    public function name(): string
    {
        return $this->sourceName;
    }
}
