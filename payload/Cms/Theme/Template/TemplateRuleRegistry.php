<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Template;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;
use InvalidArgumentException;

final class TemplateRuleRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof TemplateRuleDefinition) {
            throw new InvalidArgumentException('TemplateRuleRegistry accepts TemplateRuleDefinition only.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $definition->kind) !== 1) {
            throw new InvalidArgumentException('Invalid template rule kind.');
        }

        if ($definition->patterns === []) {
            throw new InvalidArgumentException('Template rule must define at least one pattern.');
        }

        foreach ($definition->patterns as $pattern) {
            if (
                $pattern === ''
                || str_contains($pattern, '..')
                || str_contains($pattern, '/')
                || preg_match('/^[a-z0-9{}._-]+$/', $pattern) !== 1
            ) {
                throw new InvalidArgumentException('Unsafe template candidate pattern.');
            }
        }
    }

    /** @return list<TemplateRuleDefinition> */
    public function forKind(string $kind): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn ($item): bool => $item instanceof TemplateRuleDefinition && $item->kind === $kind,
        ));
        usort($items, static fn (TemplateRuleDefinition $a, TemplateRuleDefinition $b): int =>
            [$a->priority, $a->id] <=> [$b->priority, $b->id]
        );
        return $items;
    }
}
