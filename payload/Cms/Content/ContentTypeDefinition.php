<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use InvalidArgumentException;

final readonly class ContentTypeDefinition implements OwnedDefinitionInterface
{
    /**
     * @param list<string> $fields
     * @param list<string> $taxonomies
     * @param array{read:string,create:string,update:string,delete:string,publish:string} $permissions
     * @param array<string,mixed> $rest
     * @param array<string,mixed> $search
     * @param array<string,mixed> $editor
     */
    public function __construct(
        private string $key,
        private string $owner,
        public string $label,
        public string $singularLabel,
        public array $fields = [],
        public array $taxonomies = [],
        public bool $hierarchical = false,
        public bool $revisions = true,
        public array $permissions = [
            'read' => 'content.read',
            'create' => 'content.create',
            'update' => 'content.update',
            'delete' => 'content.delete',
            'publish' => 'content.publish',
        ],
        public array $rest = ['enabled' => true],
        public array $search = ['index' => true],
        public array $editor = [],
    ) {
        new OwnerIdentifier($owner);

        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid content type key: ' . $key);
        }

        foreach (['read', 'create', 'update', 'delete', 'publish'] as $required) {
            if (!isset($permissions[$required]) || !is_string($permissions[$required])) {
                throw new InvalidArgumentException('Content type permission map is incomplete.');
            }
        }
    }

    public function identifier(): string { return $this->key; }
    public function owner(): string { return $this->owner; }
}
