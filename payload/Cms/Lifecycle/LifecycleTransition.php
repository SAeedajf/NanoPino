<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Lifecycle;

final readonly class LifecycleTransition
{
    public function __construct(
        public ExtensionState $from,
        public ExtensionState $to,
        public float $at,
        public ?string $reason = null,
    ) {
    }
}
