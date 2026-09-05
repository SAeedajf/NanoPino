<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final class AdminRouteDefinition implements OwnedDefinitionInterface
{
    /** @param array<string,mixed> $meta */
    public function __construct(
        private readonly string $identifier,
        private readonly string $owner,
        public readonly string $path,
        public readonly string $name,
        public readonly string $component,
        public readonly ?string $permission = null,
        public readonly array $meta = [],
        public readonly int $order = 100,
    ) {}

    public function identifier(): string { return $this->identifier; }
    public function owner(): string { return $this->owner; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->identifier,
            'owner' => $this->owner,
            'path' => $this->path,
            'name' => $this->name,
            'component' => $this->component,
            'permission' => $this->permission,
            'meta' => $this->meta,
            'order' => $this->order,
        ];
    }
}
