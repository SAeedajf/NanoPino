<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\DragDrop;

final readonly class BuilderDropIntent
{
    public function __construct(
        public string $sourceId,
        public ?string $targetParentId,
        public int $index,
        public ?string $slot = null,
    ) {
        if ($sourceId === '') {
            throw new \InvalidArgumentException('Drag source ID is required.');
        }
        if ($index < 0) {
            throw new \InvalidArgumentException('Drop index cannot be negative.');
        }
    }
}
