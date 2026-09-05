<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Preview;

use App\com_pinoox_cms\Model\ThemePreviewModel;

final class PinooxThemePreviewRepository implements ThemePreviewRepositoryInterface
{
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
        $model = ThemePreviewModel::create([
            'token_hash' => $tokenHash,
            'site_id' => $siteId,
            'package' => $package,
            'theme_name' => $themeName,
            'context' => $context,
            'variation' => $variation,
            'design_overrides' => $designOverrides,
            'actor_id' => $actorId,
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->hydrate($model);
    }

    public function findByTokenHash(string $tokenHash): ?ThemePreviewSession
    {
        $model = ThemePreviewModel::query()
            ->where('token_hash', $tokenHash)
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    public function revoke(int $id, string $revokedAt): bool
    {
        return ThemePreviewModel::query()
            ->where('id', $id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]) > 0;
    }

    private function hydrate(ThemePreviewModel $m): ThemePreviewSession
    {
        return new ThemePreviewSession(
            (int)$m->id,
            (string)$m->token_hash,
            (int)$m->site_id,
            (string)$m->package,
            (string)$m->theme_name,
            $m->context !== null ? (string)$m->context : null,
            $m->variation !== null ? (string)$m->variation : null,
            is_array($m->design_overrides) ? $m->design_overrides : [],
            $m->actor_id !== null ? (int)$m->actor_id : null,
            (string)$m->expires_at,
            (string)$m->created_at,
            $m->revoked_at !== null ? (string)$m->revoked_at : null,
        );
    }
}
