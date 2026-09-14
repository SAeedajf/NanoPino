<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

final readonly class WordPressThemeAsset
{
    /**
     * @param list<string> $dependencies
     * @param list<string> $references
     */
    public function __construct(
        public string $key,
        public string $path,
        public WordPressAssetKind $kind,
        public int $size,
        public string $sha256,
        public string $direction = 'both',
        public ?string $version = null,
        public array $dependencies = [],
        public array $references = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'path' => $this->path,
            'kind' => $this->kind->value,
            'size' => $this->size,
            'sha256' => $this->sha256,
            'direction' => $this->direction,
            'version' => $this->version,
            'dependencies' => $this->dependencies,
            'references' => $this->references,
        ];
    }
}
