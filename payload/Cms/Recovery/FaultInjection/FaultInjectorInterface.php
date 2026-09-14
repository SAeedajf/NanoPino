<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery\FaultInjection;

/**
 * Injectable failure seam for deterministic recovery tests.
 * Production wiring uses NullFaultInjector and does not read request input.
 */
interface FaultInjectorInterface
{
    public function checkpoint(string $point): void;
}
