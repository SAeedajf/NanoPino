# NanoPino Project Status

Current package: **0.23.28 / code 2328**.

NanoPino is a Pinoox CMS and extension platform with a Vue/Luma admin, runtime API, content/media/settings/users, themes, visual builder, extension lifecycle, recovery, search, queue/cache/storage, health, logging and performance services.

## Verified in repository CI
- Production admin build is required before source verification.
- PHP source is linted.
- Node contract/regression tests must pass.
- Public runtime routes are checked bidirectionally against machine-readable API contracts.
- Generated runtime/source parity is verified.

## Release gates still requiring target-environment evidence
- Pinoox integration E2E on the target runtime.
- Browser/WCAG validation of the production Luma/Vite build.
- Signed PINX install/update/uninstall lifecycle E2E.
- Target database migration evidence.
- Persistent native theme activation.
- Safe Mode boot-order integration.
- Production security/performance probes.

## Security status
Remote Search is fail-closed by default. R11 adds explicit configuration and an HTTPS SSRF-guarded cURL transport with DNS pinning. CSP remains report-only until the Pinoox bootstrap nonce path is proven. `platform_super` remains unchanged pending a lockout-safe transition to explicit roles.
