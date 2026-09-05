<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Update;

use App\com_pinoox_cms\Cms\Lifecycle\ExtensionState;
use App\com_pinoox_cms\Cms\Recovery\RecoveryPoint;

final class UpdateTransactionResult
{
    /** @param list<string> $rollbackErrors */
    public function __construct(
        public readonly bool $success,
        public readonly ExtensionState $state,
        public readonly RecoveryPoint $recoveryPoint,
        public readonly ?string $error = null,
        public readonly array $rollbackErrors = [],
        public readonly bool $safeModeEnabled = false,
    ) {}
}
