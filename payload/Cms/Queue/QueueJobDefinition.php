<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use Closure;

final readonly class QueueJobDefinition implements OwnedDefinitionInterface
{
    public Closure $handler;

    /** @param callable(array<string,mixed>,QueueJobContext):void $handler */
    public function __construct(
        private string $id,
        private string $owner,
        callable $handler,
        public int $maxAttempts=3,
        public int $baseBackoffSeconds=30,
        public int $timeoutSeconds=60,
        public int $maxPayloadBytes=262144,
    ) {
        if (preg_match('/^[a-z][a-z0-9._:-]{1,127}$/',$id)!==1) {
            throw new \InvalidArgumentException('Invalid queue job identifier.');
        }
        if ($maxAttempts<1 || $maxAttempts>25 || $baseBackoffSeconds<1 || $baseBackoffSeconds>86400) {
            throw new \InvalidArgumentException('Invalid queue retry policy.');
        }
        if ($timeoutSeconds<1 || $timeoutSeconds>3600 || $maxPayloadBytes<256 || $maxPayloadBytes>5_242_880) {
            throw new \InvalidArgumentException('Invalid queue job limits.');
        }
        $this->handler=Closure::fromCallable($handler);
    }

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->owner; }
}
