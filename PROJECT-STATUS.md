# NanoPino Project Status

Current package: **0.23.29 / code 2329**.

**Documentation Freeze:** candidate for R15 installability validation; Stable 1.0 is not declared.

NanoPino is a Pinoox CMS and extension platform with a Vue/Luma admin, runtime API, content/media/settings/users, themes, visual builder, extension lifecycle, recovery, search, queue/cache/storage, health, logging and performance services.

## Verified repository gates before R15
- Production Admin build is required before source verification.
- PHP runtime tests execute across PHP 8.2–8.5.
- Node contract/regression tests must pass.
- Public runtime routes are checked bidirectionally against machine-readable API contracts.
- Generated runtime/source parity is verified.
- Native Pinoox/MySQL PINX build/install/update/uninstall lifecycle is exercised in CI.

## R15 candidate gates
- Native minimum Pincore is raised to 3.10.0 / code 216.
- PINX artifact structure and release metadata are audited after native build.
- A read-only installability preflight runs before CMS schema-creating migrations.
- CI exercises both Pincore 3.10.0 and 3.14.4.
- The native update path is 0.23.28 → 0.23.29 without force.
- A separate clean 0.23.29 install/uninstall cycle is required.
- CMS table count must remain stable on update and return to zero after uninstall.

These R15 items become verified release evidence only after the R15 branch workflow is green.

## Release gates still requiring target-environment evidence
- Pinoox integration E2E on the exact target shared-hosting runtime.
- Browser/mobile/WCAG validation of the production Luma/Vite build.
- Signed PINX install/update/uninstall trust-chain E2E.
- Target database migration evidence and hosting privilege validation.
- Persistent native theme activation.
- Safe Mode real boot-order integration.
- Fault-injected filesystem/database recovery.
- Production security/performance probes.

## Security status
Remote Search is fail-closed by default and uses explicit configuration plus an HTTPS SSRF-guarded transport when enabled. CSP remains report-only until the browser bootstrap/nonce path is proven under enforcement. `platform_super` remains unchanged pending a lockout-safe transition to explicit roles.
