<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Document;

final readonly class BlockDocument
{
    /** @param list<BlockNode> $blocks */
    public function __construct(
        public int $schemaVersion,
        public array $blocks,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->schemaVersion,
            'blocks' => array_map(static fn (BlockNode $node): array => $node->toArray(), $this->blocks),
        ];
    }
}
