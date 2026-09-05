<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Inspector;

final readonly class BlockInspectorSchema
{
    /**
     * @param list<InspectorField> $attributes
     * @param list<string> $styleGroups
     * @param array<string,array<string,mixed>> $slots
     */
    public function __construct(
        public string $blockType,
        public string $title,
        public int $schemaVersion,
        public array $attributes,
        public array $styleGroups,
        public bool $responsive,
        public array $slots,
    ) {}
}
