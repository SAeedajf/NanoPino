<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

final readonly class ResolvedDesign
{
    /**
     * @param array<string,mixed> $tokens
     * @param list<string> $sources
     */
    public function __construct(
        public array $tokens,
        public array $sources,
        public ?string $variation = null,
    ) {}
}
