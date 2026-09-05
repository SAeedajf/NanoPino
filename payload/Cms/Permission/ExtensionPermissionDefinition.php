<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Permission;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final readonly class ExtensionPermissionDefinition implements OwnedDefinitionInterface
{
    public function __construct(
        private string $identifier,
        private string $owner,
        public string $description,
        public ExtensionPermissionRisk $risk,
    ) {
    }

    public function identifier(): string { return $this->identifier; }
    public function owner(): string { return $this->owner; }
}
