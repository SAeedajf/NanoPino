<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block;

final readonly class BlockAttributeDefinition
{
    /** @param array<string,mixed> $rules */
    public function __construct(
        public string $name,
        public BlockAttributeType $type,
        public bool $required = false,
        public mixed $default = null,
        public array $rules = [],
    ) {}
}
