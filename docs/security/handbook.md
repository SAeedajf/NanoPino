# Security Handbook

Current controls include output escaping/sanitization, media upload validation, package path/resource checks, extension static-risk preflight, ownership-aware authorization, strict same-origin mutation integrity, secret redaction, rate-limit/CSRF/security-header runtime bindings and API security metadata.

## Outbound requests
Remote Search is disabled unless explicit global settings select Meilisearch or Typesense and provide a valid HTTPS endpoint/index. Requests pass through `SsrfGuard`, require an explicit host/port allowlist, reject private/reserved DNS results, disable redirects and pin approved addresses with cURL `CURLOPT_RESOLVE`.

Database Search remains the fallback.

## CSP
CSP remains **report-only**. Pinoox bootstrap output currently has no proven nonce integration in this repository; enforcement must not be enabled until browser E2E proves the complete shell/bootstrap/Luma/Vite path.

## Open access gate
`platform_super=true` remains unchanged pending a lockout-safe migration to explicit privileged roles.


## CSP nonce readiness
R12 no longer depends on the Pincore `pinoox_bootstrap()` HTML helper for the NanoPino admin shell. The controller obtains the canonical Pinoox bootstrap data through `PinooxScriptHelper::bootstrap()`, JSON-encodes it with HTML-sensitive characters escaped, and renders the inline bootstrap script with the same request nonce used by the response security policy. Shell inline style elements are nonce-tagged as well.

The policy additionally declares `script-src-attr 'none'`. Vue/Luma dynamic style attributes still require `style-src-attr 'unsafe-inline'`.

CSP remains report-only until the production browser path is exercised under the target Pinoox/Luma runtime.

## Platform super transition
Pincore documents that installer-created platform accounts may omit an explicit `group_key`. NanoPino therefore does not disable `platform_super` automatically. R12 audits every platform-scoped account and reports how many already have an explicit configured super role/group and how many still depend on the implicit platform bypass. A controlled cutover is safe to attempt only when no detected account is implicit-only and an explicit super account exists.
