<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Hook;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use Closure;
use InvalidArgumentException;

final readonly class FilterDefinition implements OwnedDefinitionInterface
{
    public Closure $callback;

    /** @param callable(mixed,array<string,mixed>):mixed $callback */
    public function __construct(
        public string $hook,
        private string $name,
        private string $owner,
        callable $callback,
        public int $priority = 10,
    ) {
        new OwnerIdentifier($owner);

        if (preg_match('/^[a-z0-9][a-z0-9._:-]{1,190}$/', $hook) !== 1) {
            throw new InvalidArgumentException('Invalid filter hook: ' . $hook);
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,100}$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid filter name: ' . $name);
        }

        $this->callback = Closure::fromCallable($callback);
    }

    public function identifier(): string
    {
        return 'filter:' . $this->hook . ':' . $this->owner . ':' . $this->name;
    }

    public function owner(): string { return $this->owner; }
}
