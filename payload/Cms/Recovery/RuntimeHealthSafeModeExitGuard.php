<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use App\com_pinoox_cms\Cms\Health\HealthRunner;
use App\com_pinoox_cms\Cms\Health\HealthStatus;

final readonly class RuntimeHealthSafeModeExitGuard implements SafeModeExitGuardInterface
{
    public function __construct(private HealthRunner $health) {}

    public function canDisable(SafeModeState $state): bool
    {
        if (!$state->enabled) return true;
        foreach ($this->health->runAll() as $result) {
            if ($result->status === HealthStatus::Error) return false;
        }
        return true;
    }

    public function reasons(SafeModeState $state): array
    {
        if (!$state->enabled) return [];
        $reasons = [];
        foreach ($this->health->runAll() as $result) {
            if ($result->status === HealthStatus::Error) {
                $reasons[] = $result->id . ': ' . $result->message;
            }
        }
        return $reasons;
    }
}
