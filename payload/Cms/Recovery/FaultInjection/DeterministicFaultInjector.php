<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery\FaultInjection;

use RuntimeException;

/**
 * Test-only deterministic injector. A value of N fails the next N visits.
 * It is deliberately not constructed by runtime services or API payloads.
 */
final class DeterministicFaultInjector implements FaultInjectorInterface
{
    /** @var array<string,int> */
    private array $remaining;

    /** @var list<string> */
    private array $visited = [];

    /** @param array<string,int> $failures */
    public function __construct(array $failures = [])
    {
        $this->remaining = [];
        foreach ($failures as $point => $count) {
            if (!is_string($point) || preg_match('/^[a-z][a-z0-9_.-]{0,127}$/', $point) !== 1) {
                throw new \InvalidArgumentException('Invalid fault-injection point.');
            }
            if (!is_int($count) || $count < 1 || $count > 100) {
                throw new \InvalidArgumentException('Invalid fault-injection count.');
            }
            $this->remaining[$point] = $count;
        }
    }

    public function checkpoint(string $point): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,127}$/', $point) !== 1) {
            throw new \InvalidArgumentException('Invalid fault-injection point.');
        }
        $this->visited[] = $point;
        if (($this->remaining[$point] ?? 0) < 1) {
            return;
        }

        --$this->remaining[$point];
        throw new RuntimeException('Injected fault at ' . $point . '.');
    }

    /** @return list<string> */
    public function visited(): array
    {
        return $this->visited;
    }
}
