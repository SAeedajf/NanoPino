# Security Handbook

Current controls include output escaping/sanitization, media upload validation, package path/resource checks, extension static-risk preflight, ownership-aware authorization, strict same-origin mutation integrity, secret redaction, rate-limit/CSRF/security-header runtime bindings and API security metadata.

## Outbound requests
Remote Search is disabled unless explicit global settings select Meilisearch or Typesense and provide a valid HTTPS endpoint/index. Requests pass through `SsrfGuard`, require an explicit host/port allowlist, reject private/reserved DNS results, disable redirects and pin approved addresses with cURL `CURLOPT_RESOLVE`.

Database Search remains the fallback.

## CSP
CSP remains **report-only**. Pinoox bootstrap output currently has no proven nonce integration in this repository; enforcement must not be enabled until browser E2E proves the complete shell/bootstrap/Luma/Vite path.

## Open access gate
`platform_super=true` remains unchanged pending a lockout-safe migration to explicit privileged roles.
