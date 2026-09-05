<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final class AdminWidgetDefinition implements OwnedDefinitionInterface
{
    /** @param array<string,mixed> $props */
    public function __construct(
        private readonly string $identifier,
        private readonly string $owner,
        public readonly string $slot,
        public readonly string $component,
        public readonly ?string $permission = null,
        public readonly int $order = 100,
        public readonly array $props = [],
    ) {}

    public function identifier(): string { return $this->identifier; }
    public function owner(): string { return $this->owner; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->identifier,
            'owner' => $this->owner,
            'slot' => $this->slot,
            'component' => $this->component,
            'permission' => $this->permission,
            'order' => $this->order,
            'props' => $this->props,
        ];
    }
}
