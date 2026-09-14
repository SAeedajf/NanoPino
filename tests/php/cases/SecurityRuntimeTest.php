<?php
declare(strict_types=1);

use App\com_pinoox_cms\Cms\Security\Http\CspPolicy;
use App\com_pinoox_cms\Cms\Security\Http\SecurityHeadersPolicy;
use App\com_pinoox_cms\Cms\Security\Posture\SecurityPostureService;
use App\com_pinoox_cms\Cms\Security\Posture\SecurityRuntimeState;

return [
    'CSP policy emits nonce-backed strict script directives' => static function (): void {
        $policy = new CspPolicy();
        $nonce = $policy->nonce();

        np_assert_matches('/^[A-Za-z0-9_-]{16,64}$/', $nonce);
        np_assert_same('Content-Security-Policy-Report-Only', $policy->headerName());

        $header = $policy->header($nonce);
        np_assert_contains("script-src 'self' 'nonce-{$nonce}'", $header);
        np_assert_contains("script-src-attr 'none'", $header);
        np_assert_contains("style-src 'self' 'nonce-{$nonce}'", $header);
        np_assert_contains("style-src-attr 'unsafe-inline'", $header);
        np_assert_contains("object-src 'none'", $header);
    },

    'CSP enforcement header name is explicit when report-only is disabled' => static function (): void {
        $policy = new CspPolicy(reportOnly: false);
        np_assert_same('Content-Security-Policy', $policy->headerName());
    },

    'same-origin manager embedding is explicit and public framing stays denied' => static function (): void {
        $policy = new SecurityHeadersPolicy(new CspPolicy(reportOnly: false), hsts: false);
        $nonce = (new CspPolicy())->nonce();

        $standalone = $policy->headers($nonce, https: true);
        np_assert_same('DENY', $standalone['X-Frame-Options']);
        np_assert_contains("frame-ancestors 'none'", $standalone['Content-Security-Policy']);

        $manager = $policy->headers($nonce, https: true, allowSameOriginFrame: true);
        np_assert_same('SAMEORIGIN', $manager['X-Frame-Options']);
        np_assert_contains("frame-ancestors 'self'", $manager['Content-Security-Policy']);
    },

    'CSP rejects malformed nonces and directive injection' => static function (): void {
        $policy = new CspPolicy();
        np_assert_throws(
            static fn () => $policy->header('short'),
            InvalidArgumentException::class,
            'Invalid CSP nonce',
        );

        $badPolicy = new CspPolicy(extraDirectives: [
            'connect-src' => ["'self'; frame-src *"],
        ]);
        np_assert_throws(
            static fn () => $badPolicy->header($badPolicy->nonce()),
            InvalidArgumentException::class,
            'Invalid CSP source',
        );
    },

    'Security posture stays warning while implicit platform super is enabled' => static function (): void {
        $runtime = new SecurityRuntimeState(
            csrfVerifierBound: true,
            rateLimitsRegistered: true,
            securityHeadersBound: true,
            ssrfTransportBound: true,
            publicApiSecurityBound: true,
            cspEnforced: true,
            implicitPlatformSuperEnabled: true,
            explicitPlatformSuperReady: false,
        );

        $report = (new SecurityPostureService($runtime))->report()->toArray();
        np_assert_same('warning', $report['status']);

        $platform = array_values(array_filter(
            $report['controls'],
            static fn (array $control): bool => $control['id'] === 'authorization.platform_super',
        ));
        np_assert_same(1, count($platform));
        np_assert_same('warning', $platform[0]['status']);
    },

    'Security posture can become pass when all runtime controls are bound explicitly' => static function (): void {
        $runtime = new SecurityRuntimeState(
            csrfVerifierBound: true,
            rateLimitsRegistered: true,
            securityHeadersBound: true,
            ssrfTransportBound: true,
            publicApiSecurityBound: true,
            cspEnforced: true,
            implicitPlatformSuperEnabled: false,
            explicitPlatformSuperReady: true,
            ssrfGuardReady: true,
        );

        $report = (new SecurityPostureService($runtime))->report();
        np_assert_same('pass', $report->status()->value);
    },
];
