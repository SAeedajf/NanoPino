<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

final class InMemoryQueryProbe implements QueryProbeInterface
{
    /** @var list<QueryObservation> */
    private array $queries=[];

    public function observe(string $sql,float $durationMs): void
    {
        $this->queries[]=new QueryObservation($sql,$durationMs,microtime(true));
    }

    public function isBound(): bool { return true; }
    public function bindingError(): ?string { return null; }
    public function count(): int { return count($this->queries); }
    public function totalMs(): float
    {
        return array_sum(array_map(static fn(QueryObservation $q):float=>$q->durationMs,$this->queries));
    }
    public function observations(): array { return $this->queries; }
}
