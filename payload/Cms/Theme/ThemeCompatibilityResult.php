<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

final readonly class ThemeCompatibilityResult
{
    /** @param list<string> $issues */
    public function __construct(
        public bool $compatible,
        public array $issues = [],
    ) {}
}
