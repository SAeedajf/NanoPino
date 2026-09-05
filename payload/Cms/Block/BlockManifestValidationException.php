<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block;

use RuntimeException;

final class BlockManifestValidationException extends RuntimeException
{
    /** @param list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(implode('; ', $violations));
    }
}
