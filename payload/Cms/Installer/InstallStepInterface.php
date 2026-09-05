<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

interface InstallStepInterface
{
    public function id(): string;
    public function phase(): InstallPhase;
    public function execute(InstallContext $context): void;
    public function rollback(InstallContext $context): void;
}
