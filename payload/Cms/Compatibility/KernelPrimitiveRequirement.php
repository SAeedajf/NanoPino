<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Compatibility;

final readonly class KernelPrimitiveRequirement
{
    /** @param list<string> $methods */
    public function __construct(
        public string $id,
        public string $class,
        public array $methods = [],
        public bool $required = true,
        public string $feature = 'core',
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]{1,127}$/',$id)!==1 || $class==='') {
            throw new \InvalidArgumentException('Invalid kernel primitive requirement.');
        }
    }
}
