<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final readonly class MediaUploadCandidate
{
    public function __construct(
        public string $temporaryPath,
        public string $originalName,
        public ?string $clientMime = null,
    ) {}
}
