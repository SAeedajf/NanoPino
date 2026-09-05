<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

final readonly class InstallJournalEntry
{
    public function __construct(
        public string $step,
        public InstallPhase $phase,
        public string $status,
        public float $at,
        public ?string $message = null,
    ) {
    }
}
