# Phase 20 — Security Verification and Threat Model

## Outcome

Phase 20 consolidates the NanoPino security boundary into a versioned threat
model and verifies the controls against source contracts, the current PINX
artifact and read-only target responses. It does not claim that source review
replaces authenticated penetration testing or target lifecycle evidence.

## Trust boundaries reviewed

- Browser/cross-origin client to mounted CMS API.
- Stored Content, Builder and media data to Admin/public HTML output.
- Uploaded PINX package to the native Pinoox installer and extension runtime.
- Optional remote-search configuration to outbound network egress.
- User-controlled upload/object keys to native Pinoox storage/filesystem.

## Control verification

| Boundary | Controls verified | Result |
|---|---|---|
| API/session | Native Auth, capability flow, exact same-origin, subject-bound CSRF, native throttle profiles | PASS in source/PHP contracts |
| Privilege/scope | Site scope, ownership, author assignment and explicit-role boundary | PASS in source/PHP contracts |
| Output | Rich-text allowlist, escaped blocks, URL scheme policy, CSP/security headers | PASS in source/PHP contracts |
| Network | Opt-in remote search, host allowlist, DNS/IP public check, pinned cURL resolution | PASS in source/PHP contracts |
| Package | Canonical paths, symlinks, archive budgets, static scan, checksum/signature/review ticket | PASS; current artifact has 0 high findings and 2 warnings |
| Upload/storage | MIME/decode checks, executable rejection, size/hash/scoped native storage | PASS in source/PHP contracts |
| Recovery | Fail-closed Safe Mode, quarantine, snapshot integrity and fault checkpoints | PASS in source/PHP contracts |
| Information disclosure | Redaction, generic errors, safe correlation and no-store API responses | PASS in source/PHP contracts |

## Threat model summary

The complete STRIDE-oriented register contains 11 threats in
`resources/release/security-threat-model-phase20-v1.json`. The highest-risk
items are unauthenticated mutation, privilege/site-scope escalation and
malicious package execution. All three have source controls and explicit
remaining target verification requirements.

Important residual risks are not silently marked as solved:

- authenticated target role/session/CSRF E2E;
- signed publisher trust-chain and install/update/uninstall rehearsal;
- target database, filesystem, proxy, egress, log-retention and Safe Mode
  boot-order behavior;
- fuzzing/penetration testing and third-party extension review;
- authenticated concurrency and abuse/load testing.

## Verification evidence

| Check | Result |
|---|---|
| Frontend contract suite | PASS — 218/218 |
| PHP CMS runtime suite | PASS — 72/72 |
| Documentation audit | PASS — 31 required docs, 23 machine contracts, 62 Markdown files, 33 internal links |
| Phase 19 PINX preflight | PASS — 0 high findings, 2 warnings (`dynamic_include`, `raw_network`) |
| Target public response | HTTP 200; HSTS, nosniff, frame denial, strict referrer policy, Permissions-Policy, COOP/CORP and enforced CSP observed |
| Target CMS mount without session | HTTP 401; no credentials or state changed |
| Target Manager | HTTP 200 after redirect; read-only inspection |

The two PINX warnings are expected review signals: a dynamic include in a
native package file and the guarded remote-search cURL primitive. They do not
constitute high-risk findings in this artifact, but they remain visible for
review.

## Gate

Phase 20 is **source, artifact-preflight and target-read-only security
verified**. It does not make the release Stable. The authenticated target
security matrix, signed lifecycle, penetration/fuzz testing, target recovery
and production operational evidence remain open.

Machine-readable evidence: `resources/release/security-threat-model-phase20-v1.json`.
