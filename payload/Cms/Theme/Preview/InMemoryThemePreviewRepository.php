<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Preview;

final class InMemoryThemePreviewRepository implements ThemePreviewRepositoryInterface
{
    /** @var array<int,ThemePreviewSession> */
    private array $sessions = [];
    private int $nextId = 1;

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
    ): ThemePreviewSession {
        $session = new ThemePreviewSession(
            $this->nextId++,
            $tokenHash,
            $siteId,
            $package,
            $themeName,
            $context,
            $variation,
            $designOverrides,
            $actorId,
            $expiresAt,
            gmdate(DATE_ATOM),
        );
        $this->sessions[$session->id] = $session;
        return $session;
    }

    public function findByTokenHash(string $tokenHash): ?ThemePreviewSession
    {
        foreach ($this->sessions as $session) {
            if (hash_equals($session->tokenHash, $tokenHash)) {
                return $session;
            }
        }
        return null;
    }

    public function revoke(int $id, string $revokedAt): bool
    {
        $session = $this->sessions[$id] ?? null;
        if ($session === null) return false;

        $this->sessions[$id] = new ThemePreviewSession(
            $session->id,
            $session->tokenHash,
            $session->siteId,
            $session->package,
            $session->themeName,
            $session->context,
            $session->variation,
            $session->designOverrides,
            $session->actorId,
            $session->expiresAt,
            $session->createdAt,
            $revokedAt,
        );
        return true;
    }
}
