<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Capability;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use InvalidArgumentException;

final readonly class CapabilityDefinition implements OwnedDefinitionInterface
{
    public function __construct(
        private string $key,
        private string $owner,
        public string $description = '',
    ) {
        new OwnerIdentifier($owner);

        if (preg_match('/^[a-z0-9][a-z0-9._*-]{1,190}$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid capability key: ' . $key);
        }
    }

    public function identifier(): string { return $this->key; }
    public function owner(): string { return $this->owner; }
}
