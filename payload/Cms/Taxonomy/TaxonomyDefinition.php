<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Taxonomy;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use InvalidArgumentException;

final readonly class TaxonomyDefinition implements OwnedDefinitionInterface
{
    /**
     * @param list<string> $contentTypes
     * @param array{read:string,manage:string} $permissions
     */
    public function __construct(
        private string $key,
        private string $owner,
        public string $label,
        public string $singularLabel,
        public bool $hierarchical = false,
        public array $contentTypes = [],
        public array $permissions = [
            'read' => 'taxonomy.read',
            'manage' => 'taxonomy.manage',
        ],
        public bool $public = true,
    ) {
        new OwnerIdentifier($owner);

        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid taxonomy key: ' . $key);
        }
    }

    public function identifier(): string { return $this->key; }
    public function owner(): string { return $this->owner; }

    public function supports(string $contentType): bool
    {
        return in_array($contentType, $this->contentTypes, true);
    }
}
