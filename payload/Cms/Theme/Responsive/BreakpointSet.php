<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Responsive;

final readonly class BreakpointSet
{
    /** @param list<BreakpointDefinition> $items */
    public function __construct(public array $items) {}

    public function has(string $id): bool
    {
        foreach ($this->items as $item) {
            if ($item->id === $id) return true;
        }
        return false;
    }

    public function get(string $id): ?BreakpointDefinition
    {
        foreach ($this->items as $item) {
            if ($item->id === $id) return $item;
        }
        return null;
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_map(
            static fn (BreakpointDefinition $item): string => $item->id,
            $this->items,
        );
    }
}
