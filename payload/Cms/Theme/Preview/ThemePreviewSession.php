<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Preview;

final readonly class ThemePreviewSession
{
    /** @param array<string,mixed> $designOverrides */
    public function __construct(
        public int $id,
        public string $tokenHash,
        public int $siteId,
        public string $package,
        public string $themeName,
        public ?string $context,
        public ?string $variation,
        public array $designOverrides,
        public ?int $actorId,
        public string $expiresAt,
        public string $createdAt,
        public ?string $revokedAt = null,
    ) {}

    public function isExpired(?int $now = null): bool
    {
        $now ??= time();
        $expiry = strtotime($this->expiresAt);
        return $expiry === false || $expiry <= $now;
    }

    public function isActive(?int $now = null): bool
    {
        return $this->revokedAt === null && !$this->isExpired($now);
    }
}
