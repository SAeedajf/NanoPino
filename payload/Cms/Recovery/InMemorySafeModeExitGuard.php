<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final readonly class InMemorySafeModeExitGuard implements SafeModeExitGuardInterface
{
    /** @param list<string> $reasons */
    public function __construct(
        private bool $allowed = true,
        private array $reasons = [],
    ) {}

    public function canDisable(SafeModeState $state): bool
    {
        return !$state->enabled || $this->allowed;
    }

    public function reasons(SafeModeState $state): array
    {
        return $state->enabled && !$this->allowed
            ? ($this->reasons ?: ['System health does not allow Safe Mode exit.'])
            : [];
    }
}
