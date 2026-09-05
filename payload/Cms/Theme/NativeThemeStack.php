<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

final readonly class NativeThemeStack
{
    /**
     * @param list<string> $names
     * @param list<string> $paths
     */
    public function __construct(
        public string $package,
        public string $activeName,
        public ?string $context,
        public string $pathTheme,
        public array $names,
        public array $paths,
    ) {}
}
