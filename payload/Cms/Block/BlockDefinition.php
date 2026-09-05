<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final readonly class BlockDefinition implements OwnedDefinitionInterface
{
    /**
     * @param array<string,BlockAttributeDefinition> $attributes
     * @param array<string,mixed> $supports
     * @param list<string> $allowedChildren
     * @param list<string> $permissions
     * @param array<string,array<string,mixed>> $slots
     * @param list<array<string,mixed>> $migrations
     * @param array<string,mixed> $editor
     * @param array<string,mixed> $renderer
     */
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $name,
        public string $title,
        public string $category,
        public string $icon,
        public int $schemaVersion,
        public string $version,
        public array $attributes = [],
        public array $supports = [],
        public bool $allowsChildren = false,
        public array $allowedChildren = [],
        public array $permissions = [],
        public array $slots = [],
        public array $migrations = [],
        public array $editor = [],
        public array $renderer = [],
    ) {}

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->ownerId; }
}
