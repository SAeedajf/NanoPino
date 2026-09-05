<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use RuntimeException;

final class ThemeService
{
    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly ThemeInheritanceResolver $inheritance,
        private readonly ThemeCompatibilityChecker $compatibility,
        private readonly ThemeActivationGatewayInterface $activation,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
        private readonly string $cmsVersion,
    ) {}

    /** @return list<ThemeDefinition> */
    public function list(int $siteId, ?int $actorId = null): array
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'themes.read',
            $actorId,
            ScopeType::Site,
            $siteId,
            'theme_collection',
        ));

        return $this->themes->definitions();
    }


    /** @return array{compatibility:array{compatible:bool,issues:list<string>},inheritance:array{valid:bool,issues:list<string>,chain:list<string>}} */
    public function inspect(ThemeDefinition $theme, ?string $context = null): array
    {
        $compatibility = $this->compatibility->check($theme, $this->cmsVersion);
        $inheritanceIssues = [];
        $chain = [];

        try {
            $stack = $this->inheritance->resolve($theme->package, $theme->name, $context);
            $chain = $stack->names;
        } catch (\Throwable $error) {
            $message = trim($error->getMessage());
            $inheritanceIssues[] = $message !== '' ? $message : 'Theme inheritance validation failed.';
        }

        return [
            'compatibility' => [
                'compatible' => $compatibility->compatible,
                'issues' => $compatibility->issues,
            ],
            'inheritance' => [
                'valid' => $inheritanceIssues === [],
                'issues' => $inheritanceIssues,
                'chain' => $chain,
            ],
        ];
    }

    public function activate(
        int $siteId,
        string $package,
        string $themeName,
        ?string $context = null,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): ThemeDefinition {
        $this->authorization->authorize(new AuthorizationRequest(
            'themes.activate',
            $actorId,
            ScopeType::Site,
            $siteId,
            'theme',
            $package . ':' . $themeName,
        ));

        $theme = $this->themes->byReference($package, $themeName)
            ?? throw new RuntimeException('Theme is not registered.');

        $compatibility = $this->compatibility->check($theme, $this->cmsVersion);
        if (!$compatibility->compatible) {
            throw new RuntimeException(
                'Theme compatibility check failed: ' . implode('; ', $compatibility->issues)
            );
        }

        // Missing parents and cycles are blocking.
        $this->inheritance->resolve($package, $themeName, $context);

        $previous = $this->activation->active($package, $context);
        $this->activation->activate($package, $themeName, $context);

        $this->audit->log(
            'theme.activate',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $siteId,
            'theme',
            $theme->identifier(),
            $correlationId,
            [
                'previous' => $previous,
                'theme' => $theme->reference(),
                'context' => $context,
            ],
        );

        return $theme;
    }
}
