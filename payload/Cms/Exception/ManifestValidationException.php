<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Exception;

use InvalidArgumentException;

final class ManifestValidationException extends InvalidArgumentException
{
    /** @param list<string> $violations */
    public function __construct(private readonly array $violations)
    {
        parent::__construct('CMS extension manifest is invalid: ' . implode(' ', $violations));
    }

    /** @return list<string> */
    public function violations(): array
    {
        return $this->violations;
    }
}
