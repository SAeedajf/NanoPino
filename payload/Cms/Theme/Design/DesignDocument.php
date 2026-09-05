<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

final readonly class DesignDocument
{
    /** @param array<string,mixed> $tokens */
    public function __construct(
        public int $schemaVersion,
        public array $tokens,
    ) {}
}
