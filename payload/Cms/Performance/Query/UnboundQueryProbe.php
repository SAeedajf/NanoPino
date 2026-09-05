<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

final class UnboundQueryProbe implements QueryProbeInterface
{
    public function isBound(): bool { return false; }
    public function bindingError(): ?string { return 'Query probe is not bound.'; }
    public function count(): int { return 0; }
    public function totalMs(): float { return 0.0; }
    public function observations(): array { return []; }
}
