<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Posture;

final readonly class SecurityPostureService
{
    public function __construct(private SecurityRuntimeState $runtime = new SecurityRuntimeState()) {}

    public function report(): SecurityPostureReport
    {
        return new SecurityPostureReport([
            new SecurityControl('output.richtext','RichText output',SecurityControlStatus::Pass,'Strict allowlist sanitizer is implemented at the render boundary.'),
            new SecurityControl('output.blocks','Block output',SecurityControlStatus::Pass,'Core text/URL output is escaped and unsafe schemes/protocol-relative URLs are rejected.'),
            new SecurityControl('upload.media','Media upload',SecurityControlStatus::Pass,'Server MIME validation, decode validation, blocked executables and SHA-256 are enforced.'),
            new SecurityControl('package.paths','Package paths',SecurityControlStatus::Pass,'Traversal, symlink, collision and archive resource budgets are enforced.'),
            new SecurityControl('package.static_scan','Extension static risk scan',SecurityControlStatus::Pass,'Static-risk preflight is bound to install/update; High findings fail closed while scans remain advisory for lower severities.'),
            new SecurityControl('authorization.content_ownership','Content ownership boundary',SecurityControlStatus::Pass,'Cross-author content read/mutation requires content.manage_others; explicit author assignment requires content.assign_author.'),
            new SecurityControl('request.same_origin','Mutation origin boundary',SecurityControlStatus::Pass,'CSRF integrity accepts only same-origin Fetch Metadata or an exact Origin/Referer origin; same-site sibling origins are not trusted.'),
            new SecurityControl('secrets.redaction','Sensitive data redaction',SecurityControlStatus::Pass,'Credential-like keys/tokens/private keys have a centralized redaction policy.'),
            $this->runtimeControl('request.csrf','CSRF verifier',$this->runtime->csrfVerifierBound,'Request integrity policy exists, but production CSRF verification must be bound to the Pinoox runtime.'),
            $this->runtimeControl('abuse.rate_limit','Rate limits',$this->runtime->rateLimitsRegistered,'CMS profiles target native Pinoox RateLimiter/ThrottleFlow; runtime registration is required.'),
            $this->runtimeControl('http.headers','Security headers',$this->runtime->securityHeadersBound,'Security header/CSP policy exists; response binding is required.'),
            $this->runtimeControl('network.ssrf','Outbound SSRF guard',$this->runtime->ssrfTransportBound,'Allowlist/DNS/IP guard exists; remote transports must bind it before network calls.'),
            $this->runtimeControl('api.security','Public API security pipeline',$this->runtime->publicApiSecurityBound,'Public HTTP routes must bind auth, request integrity and native throttle flows.'),
            new SecurityControl(
                'http.csp_mode',
                'CSP enforcement',
                $this->runtime->cspEnforced ? SecurityControlStatus::Pass : SecurityControlStatus::Warning,
                $this->runtime->cspEnforced
                    ? 'CSP is enforced.'
                    : 'CSP policy starts in report-only integration mode until Luma/Vite browser E2E is verified.',
            ),
        ]);
    }

    private function runtimeControl(string $id,string $label,bool $bound,string $warning): SecurityControl
    {
        return new SecurityControl(
            $id,
            $label,
            $bound ? SecurityControlStatus::Pass : SecurityControlStatus::Warning,
            $bound ? $label . ' is bound to runtime.' : $warning,
        );
    }
}
