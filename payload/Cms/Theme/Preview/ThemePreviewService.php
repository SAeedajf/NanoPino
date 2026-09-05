<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Preview;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Theme\Design\DesignSchemaValidator;
use App\com_pinoox_cms\Cms\Theme\ThemeInheritanceResolver;
use App\com_pinoox_cms\Cms\Theme\ThemeRegistry;

final class ThemePreviewService
{
    public function __construct(
        private readonly ThemePreviewRepositoryInterface $repository,
        private readonly ThemeRegistry $themes,
        private readonly ThemeInheritanceResolver $inheritance,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
        private readonly DesignSchemaValidator $designValidator = new DesignSchemaValidator(),
    ) {}

    /** @param array<string,mixed> $designOverrides */
    public function create(
        int $siteId,
        string $package,
        string $themeName,
        ?string $context = null,
        ?string $variation = null,
        array $designOverrides = [],
        int $ttlSeconds = 1800,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): ThemePreviewTicket {
        $this->authorization->authorize(new AuthorizationRequest(
            'themes.preview',
            $actorId,
            ScopeType::Site,
            $siteId,
            'theme',
            $package . ':' . $themeName,
        ));

        if ($ttlSeconds < 60 || $ttlSeconds > 7200) {
            throw new ThemePreviewException('Theme preview TTL must be between 60 and 7200 seconds.');
        }

        if ($this->themes->byReference($package, $themeName) === null) {
            throw new ThemePreviewException('Theme is not registered.');
        }

        // Parent existence/cycle gate before issuing the token.
        $this->inheritance->resolve($package, $themeName, $context);

        if ($variation !== null && preg_match('/^[a-z0-9][a-z0-9._-]{0,95}$/', $variation) !== 1) {
            throw new ThemePreviewException('Invalid style variation.');
        }

        if ($designOverrides !== []) {
            $validated = $this->designValidator->validate(['schema' => 1, 'tokens' => $designOverrides]);
            $designOverrides = $validated->tokens;

            $json = json_encode($designOverrides, JSON_THROW_ON_ERROR);
            if (strlen($json) > 131072) {
                throw new ThemePreviewException('Preview design overrides exceed 128 KiB.');
            }
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);

        $session = $this->repository->create(
            $hash,
            $siteId,
            $package,
            $themeName,
            $context,
            $variation,
            $designOverrides,
            $actorId,
            $expiresAt,
        );

        $this->audit->log(
            'theme.preview.create',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $siteId,
            'theme_preview',
            $session->id,
            $correlationId,
            [
                'theme' => $package . ':' . $themeName,
                'context' => $context,
                'variation' => $variation,
                'expires_at' => $expiresAt,
            ],
        );

        return new ThemePreviewTicket($token, $session);
    }

    public function resolve(string $token, ?int $expectedSiteId = null): ThemePreviewSession
    {
        if (strlen($token) < 40 || strlen($token) > 128) {
            throw new ThemePreviewException('Invalid preview token.');
        }

        $hash = hash('sha256', $token);
        $session = $this->repository->findByTokenHash($hash);

        if ($session === null || !$session->isActive()) {
            throw new ThemePreviewException('Theme preview session is missing, expired or revoked.');
        }

        if ($expectedSiteId !== null && $session->siteId !== $expectedSiteId) {
            throw new ThemePreviewException('Theme preview session belongs to another site.');
        }

        return $session;
    }

    public function revoke(
        string $token,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): void {
        $session = $this->resolve($token);

        $this->authorization->authorize(new AuthorizationRequest(
            'themes.preview',
            $actorId,
            ScopeType::Site,
            $session->siteId,
            'theme_preview',
            $session->id,
            $session->actorId,
        ));

        if (!$this->repository->revoke($session->id, gmdate('Y-m-d H:i:s'))) {
            throw new ThemePreviewException('Theme preview could not be revoked.');
        }

        $this->audit->log(
            'theme.preview.revoke',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $session->siteId,
            'theme_preview',
            $session->id,
            $correlationId,
        );
    }
}
