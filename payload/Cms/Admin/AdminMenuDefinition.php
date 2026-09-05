<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final class AdminMenuDefinition implements OwnedDefinitionInterface
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $owner,
        public readonly string $label,
        public readonly string $route,
        public readonly string $icon = 'circle',
        public readonly ?string $permission = null,
        public readonly ?string $parent = null,
        public readonly string $section = 'main',
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
            'label' => $this->label,
            'route' => $this->route,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'parent' => $this->parent,
            'section' => $this->section,
            'order' => $this->order,
        ];
    }
}
