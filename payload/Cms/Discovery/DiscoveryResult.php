<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Discovery;

final readonly class DiscoveryResult
{
    /**
     * @param list<DiscoveredExtension> $extensions
     * @param list<DiscoveryProblem> $problems
     */
    public function __construct(
        public array $extensions,
        public array $problems,
    ) {
    }

    public function isClean(): bool
    {
        return $this->problems === [];
    }
}
