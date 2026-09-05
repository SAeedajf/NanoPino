<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer\Filesystem;

final readonly class DirectorySwitchReceipt
{
    public function __construct(
        public string $destination,
        public string $staging,
        public ?string $backup,
        public bool $freshInstall,
    ) {
    }
}
