<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final class AdminPanelDefinition implements OwnedDefinitionInterface
{
    /** @param array<string,mixed> $meta */
    public function __construct(
        private readonly string $identifier,
        private readonly string $owner,
        public readonly AdminSurface $surface,
        public readonly string $component,
        public readonly ?string $permission = null,
        public readonly int $order = 100,
        public readonly array $meta = [],
    ) {
        if (!in_array($surface, [AdminSurface::SettingsPanel, AdminSurface::EditorPanel, AdminSurface::Command], true)) {
            throw new \InvalidArgumentException('AdminPanelDefinition requires settings/editor/command surface.');
        }
    }

    public function identifier(): string { return $this->identifier; }
    public function owner(): string { return $this->owner; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->identifier,
            'owner' => $this->owner,
            'surface' => $this->surface->value,
            'component' => $this->component,
            'permission' => $this->permission,
            'order' => $this->order,
            'meta' => $this->meta,
        ];
    }
}
