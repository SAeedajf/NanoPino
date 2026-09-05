<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Command;

interface BlockNodeIdGeneratorInterface
{
    public function next(string $sourceId): string;
}
