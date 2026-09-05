<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

final readonly class DependencyIssue
{
    public function __construct(
        public string $code,
        public string $target,
        public string $message,
        public bool $blocking,
    ) {
    }
}
