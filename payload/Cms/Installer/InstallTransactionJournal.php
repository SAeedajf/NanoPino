<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

final class InstallTransactionJournal
{
    /** @var list<InstallJournalEntry> */
    private array $entries = [];

    public function record(string $step, InstallPhase $phase, string $status, ?string $message = null): void
    {
        $this->entries[] = new InstallJournalEntry($step, $phase, $status, microtime(true), $message);
    }

    /** @return list<InstallJournalEntry> */
    public function entries(): array { return $this->entries; }
}
