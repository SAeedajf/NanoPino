# Phase 7 — Security, Recovery and Extension

Date: 2026-09-12

## Scope

This phase hardens the existing native Pinoox CMS control planes. It does not create a parallel installer, permission system, recovery store, or extension loader.

## Delivered

- Security Center labels and statuses now resolve through the native Admin language catalog. Runtime refresh exposes an accessible busy boundary and assertive error announcement.
- Recovery actions require an explicit confirmation, use a single-flight action lock, expose button-level loading state, and separate success from dismissible error feedback. Restore remains disabled when the API is unbound or the point is not restorable.
- Extension Center now exposes Inspect progress, prevents overlapping inspect/install/row operations, shows loading state on the active action, localizes package type labels and removes remaining hard-coded Repair/Uninstall labels.
- Existing native backend boundaries remain authoritative: permission checks, CSRF/integrity policy, package preflight, recovery point/snapshot policy, Safe Mode quarantine, and structured API error responses.

## Evidence

- `npm test`: 189 passed, 0 failed.
- `npm run build`: production Vite build passed; source/dist parity, 62 reachable assets, and no root-domain `/assets/` references passed.
- `php8.4 tests/php/run.php`: 64 passed, 0 failed.
- PHP lint passed for both updated language catalogs.
- Local smoke requests to `/nano`, `/nano/api/v1/cms/system/health`, `/nano/site`, `:8080/nano`, and `:8081/nano` returned 404. Authenticated browser/API E2E is therefore **UNVERIFIED** in this environment.
- The existing `releases/NanoPino-0.23.71-phase8-filesystem-recovery.pinx` was not overwritten or relabeled. A new PINX build, signed trust-chain validation, native install/update/rollback, and target-host deployment remain separate release actions.
