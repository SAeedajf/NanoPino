<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Document;

final readonly class BlockNode
{
    /**
     * @param array<string,mixed> $attributes
     * @param array<string,mixed> $styles
     * @param array<string,mixed> $responsive
     * @param list<BlockNode> $children
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $version,
        public array $attributes = [],
        public array $styles = [],
        public array $responsive = [],
        public array $children = [],
        public ?string $slot = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'version' => $this->version,
            'attributes' => $this->attributes,
            'styles' => $this->styles,
            'responsive' => $this->responsive,
            'children' => array_map(static fn (BlockNode $node): array => $node->toArray(), $this->children),
            'slot' => $this->slot,
        ];
    }
}
