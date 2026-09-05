<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use InvalidArgumentException;

final readonly class RoleTemplateDefinition implements OwnedDefinitionInterface
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        private string $key,
        private string $owner,
        public string $name,
        public string $description,
        public array $capabilities,
    ) {
        new OwnerIdentifier($owner);

        if (preg_match('/^[a-z0-9][a-z0-9_.-]{1,100}$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid role template key: ' . $key);
        }

        foreach ($capabilities as $capability) {
            if (!is_string($capability) || trim($capability) === '') {
                throw new InvalidArgumentException('Role template capabilities must be non-empty strings.');
            }
        }
    }

    public function identifier(): string { return $this->key; }
    public function owner(): string { return $this->owner; }
}
