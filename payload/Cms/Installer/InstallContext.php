<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

use App\com_pinoox_cms\Cms\Lifecycle\ExtensionLifecycleStateMachine;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;

final class InstallContext
{
    /** @var array<string,mixed> */
    public array $data = [];

    public function __construct(
        public readonly string $transactionId,
        public readonly ExtensionManifest $manifest,
        public readonly PackageFilePlan $filePlan,
        public readonly ExtensionLifecycleStateMachine $lifecycle,
    ) {
    }
}
