<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Command;

final class RandomBlockNodeIdGenerator implements BlockNodeIdGeneratorInterface
{
    public function next(string $sourceId): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_-]+/', '-', $sourceId) ?: 'block';
        $prefix = substr(trim($prefix, '-'), 0, 72);

        return ($prefix !== '' ? $prefix : 'block') . '-' . bin2hex(random_bytes(8));
    }
}
