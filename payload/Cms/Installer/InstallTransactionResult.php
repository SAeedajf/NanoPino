<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

use App\com_pinoox_cms\Cms\Lifecycle\ExtensionState;

final readonly class InstallTransactionResult
{
    /** @param list<string> $rollbackErrors */
    public function __construct(
        public bool $success,
        public ExtensionState $state,
        public InstallTransactionJournal $journal,
        public ?string $error = null,
        public array $rollbackErrors = [],
    ) {
    }
}
