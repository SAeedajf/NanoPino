<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Binding;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final readonly class DataSourceDefinition implements OwnedDefinitionInterface
{
    /** @param list<string> $capabilities */
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $label,
        public bool $serverOnly = true,
        public array $capabilities = [],
    ) {}

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->ownerId; }
}
