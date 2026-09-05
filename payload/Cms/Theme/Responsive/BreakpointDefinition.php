<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Responsive;

final readonly class BreakpointDefinition
{
    public function __construct(
        public string $id,
        public string $minWidth,
        public float $sortValue,
        public string $unit,
    ) {}
}
