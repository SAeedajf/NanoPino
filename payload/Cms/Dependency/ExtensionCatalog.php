<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

final class ExtensionCatalog
{
    /** @var array<string,InstalledExtension> */
    private array $items = [];

    public function add(InstalledExtension $extension): void
    {
        $this->items[$extension->identifier] = $extension;
    }

    /** @return list<InstalledExtension> */
    public function all(): array
    {
        return array_values($this->items);
    }

    /** @return list<InstalledExtension> */
    public function find(string $target): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (InstalledExtension $item): bool => $item->supplies($target),
        ));
    }

    public function has(string $target): bool
    {
        return $this->find($target) !== [];
    }
}
