<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final readonly class ValidatedMediaUpload
{
    public function __construct(
        public string $path,
        public string $originalName,
        public string $extension,
        public string $mime,
        public MediaKind $kind,
        public int $size,
        public string $sha256,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
