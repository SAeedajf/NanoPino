<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

final readonly class NativeThemeDescriptor
{
    /**
     * @param list<string> $extends
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public string $package,
        public string $name,
        public string $title,
        public string $description,
        public string $version,
        public int $versionCode,
        public array $extends,
        public string $cover,
        public string $path,
        public array $raw,
    ) {}
}
