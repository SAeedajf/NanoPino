<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery\FaultInjection;

final readonly class NullFaultInjector implements FaultInjectorInterface
{
    public function checkpoint(string $point): void
    {
    }
}
