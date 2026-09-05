<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Template;

final readonly class ResolvedTemplate
{
    public function __construct(
        public string $logicalName,
        public string $file,
        public string $themePath,
        public int $stackIndex,
    ) {}
}
