<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

final class InMemoryIdempotencyStore implements IdempotencyStoreInterface
{
    /** @var array<string,AbilityExecutionResult> */
    private array $results = [];

    public function get(string $ability, string $key): ?AbilityExecutionResult
    {
        return $this->results[$ability . '|' . $key] ?? null;
    }

    public function put(string $ability, string $key, AbilityExecutionResult $result): void
    {
        $this->results[$ability . '|' . $key] = $result;
    }
}
