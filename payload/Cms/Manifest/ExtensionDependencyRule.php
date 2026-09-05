<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Manifest;

final readonly class ExtensionDependencyRule
{
    public function __construct(
        public string $package,
        public string $constraint = '*',
        public bool $optional = false,
    ) {
    }

    /** @return array{package:string,constraint:string,optional:bool} */
    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'constraint' => $this->constraint,
            'optional' => $this->optional,
        ];
    }
}
