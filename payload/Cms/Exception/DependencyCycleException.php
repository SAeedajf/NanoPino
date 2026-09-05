<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Exception;

use RuntimeException;

final class DependencyCycleException extends RuntimeException
{
    /** @param list<string> $identifiers */
    public function __construct(private readonly array $identifiers)
    {
        parent::__construct('Circular CMS extension dependency detected: ' . implode(', ', $identifiers));
    }

    /** @return list<string> */
    public function identifiers(): array { return $this->identifiers; }
}
