<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

final class RecoveryBootGuard
{
    /** @param list<string> $alwaysAllowed */
    public function __construct(
        private readonly SafeModeManager $safeMode,
        private readonly array $alwaysAllowed = ['com_pinoox_cms', 'com_pinoox_manager'],
    ) {}

    public function mayBoot(string $extensionId): bool
    {
        return $this->safeMode->mayBoot(
            $extensionId,
            in_array($extensionId, $this->alwaysAllowed, true),
        );
    }

    public function assertMayBoot(string $extensionId): void
    {
        if (!$this->mayBoot($extensionId)) {
            throw new \RuntimeException('Extension is blocked by CMS Safe Mode.');
        }
    }
}
