<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

interface QueryProbeInterface
{
    public function isBound(): bool;
    public function bindingError(): ?string;
    public function count(): int;
    public function totalMs(): float;

    /** @return list<QueryObservation> */
    public function observations(): array;
}
