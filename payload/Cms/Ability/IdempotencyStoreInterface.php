<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

interface IdempotencyStoreInterface
{
    public function get(string $ability, string $key): ?AbilityExecutionResult;
    public function put(string $ability, string $key, AbilityExecutionResult $result): void;
}
