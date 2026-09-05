<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

use RuntimeException;

final readonly class ArrayPayloadReader implements PackagePayloadReaderInterface
{
    /** @param array<string,string> $payload */
    public function __construct(private array $payload)
    {
    }

    public function read(string $canonicalPath): string
    {
        if (!array_key_exists($canonicalPath, $this->payload)) {
            throw new RuntimeException('Package payload entry not found: ' . $canonicalPath);
        }
        return $this->payload[$canonicalPath];
    }
}
