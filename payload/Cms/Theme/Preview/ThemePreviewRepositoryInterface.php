<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Preview;

interface ThemePreviewRepositoryInterface
{
    /** @param array<string,mixed> $designOverrides */
    public function create(
        string $tokenHash,
        int $siteId,
        string $package,
        string $themeName,
        ?string $context,
        ?string $variation,
        array $designOverrides,
        ?int $actorId,
        string $expiresAt,
    ): ThemePreviewSession;

    public function findByTokenHash(string $tokenHash): ?ThemePreviewSession;
    public function revoke(int $id, string $revokedAt): bool;
}
