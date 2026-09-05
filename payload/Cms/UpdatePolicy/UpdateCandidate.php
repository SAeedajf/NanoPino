<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

final readonly class UpdateCandidate
{
    public function __construct(
        public string $extensionId,
        public string $version,
        public int $versionCode,
        public UpdateChannel $channel,
        public bool $security,
        public string $downloadReference,
        public ?string $releaseNotes = null,
    ) {
        if ($versionCode < 0) {
            throw new \InvalidArgumentException('Invalid update candidate version code.');
        }
    }
}
