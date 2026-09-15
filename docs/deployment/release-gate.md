# Release Gate

A NanoPino release candidate is acceptable for merge only when repository CI passes the production build and source verification.

Stable/production claims additionally require target-environment evidence for install/upgrade/rollback, database migrations, admin browser behavior/accessibility, native theme activation, security pipeline, Safe Mode recovery and performance/health probes.

Known R11 non-closed gates:
- CSP is enforced by default; a controlled `PINOOX_CMS_CSP_MODE=report-only` rollback is allowed only while diagnosing a browser compatibility issue;
- full database snapshot/restore is not implemented;
- `platform_super` is not yet migrated to an explicit-role-only policy.

A green CI result proves repository consistency; it does not by itself prove the target hosting environment.

## Phase 21 final RC snapshot

The 0.23.73 / code 2373 candidate is locally technically ready after the
frontend, PHP, documentation, Vite, source/runtime parity, release metadata,
security preflight and PINX archive checks pass. This does not override the
10 declared Stable blockers: target integration/database lifecycle, signed
PINX lifecycle, authenticated browser/WCAG, Theme persistence, Safe Mode
boot-order, deployment health, production security/performance and Admin i18n
evidence remain required. See the Phase 21 audit and its machine-readable
evidence for the exact snapshot.

## Phase 22 promotion and support gate

Phase 22 adds a typed, fail-closed decision boundary for `canary` and
`stable`. Both channels require a signed artifact, verified archive, zero High
security findings, a passing bounded Canary health window and a ready
post-release support plan. Stable additionally requires a recorded Canary
promotion, a closed Canary window and explicit Stable approval. The policy is
decision-only and performs no installation, deployment, migration or target
mutation. See `docs/deployment/canary-stable-support.md` and
`resources/release/stable-canary-support-phase22-v1.json`.


## R12 access/CSP cutover gates
Before switching CSP from report-only to enforce, capture a browser E2E run of the production admin shell, Vite assets, Luma components, Builder, Settings and Security pages with zero blocking CSP violations.

Before setting `platform_super=false`, Security Center must report zero implicit-only platform accounts and at least one explicit super account. The current administrator session must then be revalidated in the target environment after the configuration change. NanoPino intentionally does not auto-grant a wildcard role as part of this transition.


## R13 PHP runtime gate
The historical compatibility matrix included PHP 8.2, 8.3, 8.4 and 8.5. The
current checkout's release workflow runs the executable PHP suite on PHP 8.3
and does not claim the full historical matrix.

This closes the previous “PHP lint only” gap for framework-independent NanoPino domain behavior. It does not replace controlled Pinoox/database integration, signed PINX lifecycle testing or browser E2E; those remain Stable release gates.


## R14 native PINX lifecycle gate

The native Pinoox/MySQL lifecycle is executed by `.github/workflows/validate.yml`
after source and production-dist verification. The separate quality workflow
continues to provide the fast source/frontend gate.

The current R14 workflow requires a clean Pinoox + MySQL lifecycle after source verification. A candidate cannot pass unless native platform installation, PINX build, fresh install, versioned update and uninstall all complete successfully and the CMS table count remains stable across update and returns to zero after uninstall. The test matrix runs both Pincore 3.14.0 and 3.14.4, and fault injection is enabled on the latter.

The lifecycle creates an ephemeral Ed25519 publisher key, registers the public
key in the isolated Pinoox trust configuration, signs the current, upgrade-base
and fault-injection packages, and requires signature verification during native
installation. This closes the repository-level signed lifecycle gate while
target trust-store continuity and target-host/browser E2E remain separate Stable
gates.


## R15 installability and versioned-upgrade gate

R15 superseded the R14 same-version force-update probe with a versioned native lifecycle. Its historical workflow installed the previous official NanoPino 0.23.28 release and upgraded it to 0.23.29 **without force**, then performed a separate clean 0.23.29 fresh install/uninstall cycle.

The lifecycle matrix runs against:
- Pincore 3.14.0 / native version code 232 — the declared minimum supported kernel;
- Pincore 3.14.4 — the current verified baseline;
- MySQL 8.4.

Release packaging is additionally blocked unless the built PINX passes the R15 artifact audit. The audit verifies package/version/minpin parity, required runtime files, Vite/build evidence, release metadata hashes, safe archive paths, bounded resource use and absence of development-only Admin source/build files.

NanoPino also ships a read-only environment preflight as the first package migration. This runs before CMS schema-creating migrations and blocks unsupported PHP/Pincore/database/filesystem conditions.

R15 materially strengthens installability evidence but does not close signed PINX trust-chain validation, fault-injected database/filesystem recovery, authenticated browser/mobile/WCAG E2E or exact target shared-hosting validation.


## R16 fault-injected install recovery gate

The canonical Pincore 3.14.4 lifecycle additionally builds a test-only NanoPino PINX containing a final migration that intentionally throws after the normal schema migrations have completed.

The gate requires the failed migration to remain absent from Pinoox migration history. It then accepts either of two safe outcomes:

- Pincore has already removed the extracted application and restored NanoPino database state automatically; or
- the failed install remains recoverable through the native NanoPino/Pinoox uninstall path, which must remove the application directory, all NanoPino `cms_*` tables and NanoPino migration history.

A state where database changes remain but the application directory needed for native recovery is missing is a release failure.

This gate deliberately does not patch Pincore or claim transactionality that the native installer does not provide. It proves that NanoPino remains recoverable after a late package-migration failure in the controlled CI environment.
