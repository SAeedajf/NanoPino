<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

final readonly class NativePinxDependencyRule
{
    public function __construct(
        public string $package,
        public bool $optional = false,
        public ?int $minCode = null,
    ) {
    }
}
