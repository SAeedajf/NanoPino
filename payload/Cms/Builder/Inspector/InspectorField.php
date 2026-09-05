<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Inspector;

final readonly class InspectorField
{
    /** @param array<string,mixed> $rules */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public bool $required = false,
        public mixed $default = null,
        public array $rules = [],
        public bool $responsive = false,
    ) {}
}
