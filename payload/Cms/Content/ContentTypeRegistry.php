<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class ContentTypeRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof ContentTypeDefinition) {
            throw new InvalidArgumentException('ContentTypeRegistry accepts only ContentTypeDefinition.');
        }
    }

    public function definition(string $key): ?ContentTypeDefinition
    {
        $definition = $this->get($key);
        return $definition instanceof ContentTypeDefinition ? $definition : null;
    }

    /** @return list<ContentTypeDefinition> */
    public function definitions(): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($item): bool => $item instanceof ContentTypeDefinition,
        ));
        usort($items, static fn (ContentTypeDefinition $a, ContentTypeDefinition $b): int =>
            $a->identifier() <=> $b->identifier()
        );
        return $items;
    }
}
