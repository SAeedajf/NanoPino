<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Binding;

final readonly class DataBindingResult
{
    /**
     * @param mixed $value
     * @param array<string,int|bool|string|null> $pagination
     * @param list<string> $cacheTags
     */
    public function __construct(
        public string $source,
        public string $path,
        public mixed $value,
        public array $pagination = [],
        public array $cacheTags = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'path' => $this->path,
            'value' => $this->value,
            'pagination' => $this->pagination,
            'cache_tags' => $this->cacheTags,
        ];
    }
}
