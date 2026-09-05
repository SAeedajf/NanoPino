<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

interface SafeModeExitGuardInterface
{
    public function canDisable(SafeModeState $state): bool;

    /** @return list<string> */
    public function reasons(SafeModeState $state): array;
}
