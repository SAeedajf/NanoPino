<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

final class InMemoryExtensionOperationRepository implements ExtensionOperationRepositoryInterface
{
    /** @var array<string,ExtensionOperationRecord> */
    private array $items = [];

    public function save(ExtensionOperationRecord $operation): void
    {
        $this->items[$operation->id] = $operation;
    }

    public function find(string $id): ?ExtensionOperationRecord
    {
        return $this->items[$id] ?? null;
    }

    public function forExtension(string $extensionId, int $limit = 50): array
    {
        $items = array_values(array_filter(
            $this->items,
            static fn (ExtensionOperationRecord $item): bool => $item->extensionId === $extensionId,
        ));
        usort($items, static fn (ExtensionOperationRecord $a, ExtensionOperationRecord $b): int =>
            $b->createdAt <=> $a->createdAt
        );
        return array_slice($items, 0, max(1, min(200, $limit)));
    }

    public function activeForExtension(string $extensionId): ?ExtensionOperationRecord
    {
        foreach ($this->forExtension($extensionId, 200) as $item) {
            if (!$item->status->terminal()) return $item;
        }
        return null;
    }
}
