<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Migration;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use Closure;

final readonly class BlockMigrationDefinition implements OwnedDefinitionInterface
{
    /**
     * @param Closure(array<string,mixed>):array<string,mixed> $migrate
     */
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $blockType,
        public int $fromVersion,
        public int $toVersion,
        public Closure $migrate,
    ) {}

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->ownerId; }
}
