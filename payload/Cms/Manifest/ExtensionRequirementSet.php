<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Manifest;

final readonly class ExtensionRequirementSet
{
    /** @param array<string,string> $constraints */
    public function __construct(private array $constraints = [])
    {
    }

    public function get(string $platform): ?string
    {
        return $this->constraints[$platform] ?? null;
    }

    public function has(string $platform): bool
    {
        return isset($this->constraints[$platform]);
    }

    /** @return array<string,string> */
    public function all(): array
    {
        return $this->constraints;
    }
}
