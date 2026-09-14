# Phase 16 — Signed Plugin and Extension Lifecycle

## Outcome

Phase 16 is source-verified and locally integrated. NanoPino now treats a
valid Pinoox cryptographic package signature as mandatory at every install and
update execution boundary. Package inspection still reports trust evidence
separately, but an unsigned or invalidly signed package cannot receive an
execution path through the Extension Center.

Pinoox remains the owner of PINX installation, activation, deactivation and
uninstallation. NanoPino adds the CMS review, permission, static-risk,
signature and recovery gates around that native lifecycle without replacing
the platform installer.

## Signed trust gate

- PINX inspection verifies the archive digest, manifest and native signature
  when present, then exposes publisher and key-id evidence to the review
  projection.
- Unsigned packages remain inspectable for an explicit trust explanation but
  are blocked from signed review execution.
- Invalid signatures are failed trust, not approval-required warnings.
- Install and update APIs, the service boundary and the native executor all
  use signed-by-default behavior; caller options cannot downgrade the native
  `require_signature` gate.
- Update policy defaults to `require_signature=true`; the Admin control shows
  this as mandatory rather than an optional safety toggle.

## Approval, permissions and identity

- Review tickets remain bound to the exact staged package SHA-256, extension
  identity, expiry and one-time consumption state.
- Publisher changes, new permissions, downgrades and high-risk permissions
  still require explicit approval; unknown permissions or high static-risk
  findings block the operation.
- Successful install/update records the approved permission grant against the
  exact package digest. Successful uninstall revokes that grant.

## Recovery and Safe Mode

- Native install/update/uninstall remains wrapped by filesystem and migration
  recovery points where applicable.
- Failed native operations attempt automatic restoration before returning a
  failed operation.
- If restoration is incomplete, the package is disabled when possible and
  Safe Mode is enabled with the extension and recovery-point identity.
- CMS core cannot be deactivated or uninstalled through the extension
  lifecycle, and themes remain managed by Appearance.

## Verification

- PHP CMS runtime suite: **70/70 PASS**.
- Frontend contract suite: **203/203 PASS**.
- Production Vite build: **PASS**, 62 reachable assets and source/runtime
  parity verified.
- Package preflight and archive integrity: **PASS**.
- Unsigned trust and signed execution gate regression: **PASS**.
- Target `test.boxpdf.ir`: read-only boundary only; no package upload,
  install, update, activation, uninstall, rollback or Safe Mode mutation was
  executed.

## Release decision

The source candidate remains **0.23.72 / code 2372**. Phase 16 is complete in
the local source boundary. Production readiness still requires a signed PINX
install/update/uninstall rehearsal on the authorized target, plus the existing
target database, recovery, browser/WCAG, security and performance evidence.
