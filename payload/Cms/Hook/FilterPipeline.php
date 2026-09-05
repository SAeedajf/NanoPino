<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Hook;

final readonly class FilterPipeline
{
    public function __construct(private FilterRegistry $registry)
    {
    }

    /** @param array<string,mixed> $context */
    public function apply(string $hook, mixed $value, array $context = []): mixed
    {
        foreach ($this->registry->forHook($hook) as $filter) {
            /** @var FilterDefinition $filter */
            $value = ($filter->callback)($value, $context);
        }

        return $value;
    }
}
