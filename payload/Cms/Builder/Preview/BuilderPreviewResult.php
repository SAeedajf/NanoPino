<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Preview;

final readonly class BuilderPreviewResult
{
    /** @param list<string> $cacheTags */
    public function __construct(
        public string $html,
        public string $checksum,
        public array $cacheTags = [],
    ) {}
}
