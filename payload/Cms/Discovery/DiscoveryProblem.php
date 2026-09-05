<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

final readonly class DiscoveryProblem
{
    /** @param list<string> $violations */
    public function __construct(
        public string $source,
        public int $index,
        public array $violations,
    ) {
    }
}
