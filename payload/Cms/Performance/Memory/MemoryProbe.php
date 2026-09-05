<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Memory;

final class MemoryProbe
{
    public function snapshot(): MemorySnapshot
    {
        return new MemorySnapshot(
            memory_get_usage(false),
            memory_get_peak_usage(false),
            memory_get_usage(true),
            memory_get_peak_usage(true),
        );
    }
}
