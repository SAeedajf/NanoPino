<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Driver;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use Closure;

final readonly class DriverDefinition implements OwnedDefinitionInterface
{
    public Closure $factory;

    /**
     * @param callable():object $factory
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        private string $id,
        private string $owner,
        public DriverKind $kind,
        public string $label,
        callable $factory,
        public array $metadata = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]{1,127}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Invalid driver identifier.');
        }
        if ($label === '' || strlen($label) > 120) {
            throw new \InvalidArgumentException('Invalid driver label.');
        }
        $this->factory = Closure::fromCallable($factory);
    }

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->owner; }

    public function create(): object
    {
        return ($this->factory)();
    }
}
