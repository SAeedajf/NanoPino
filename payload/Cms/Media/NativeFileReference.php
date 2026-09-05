<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final readonly class NativeFileReference
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $id,
        public ?string $hashId,
        public ?string $url,
        public ?string $thumb,
        public ?string $path,
        public ?string $disk,
        public ?string $access,
        public array $metadata = [],
    ) {}
}
