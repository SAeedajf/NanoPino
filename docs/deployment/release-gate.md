# Release Gate

A NanoPino release candidate is acceptable for merge only when repository CI passes the production build and source verification.

Stable/production claims additionally require target-environment evidence for install/upgrade/rollback, database migrations, admin browser behavior/accessibility, native theme activation, security pipeline, Safe Mode recovery and performance/health probes.

Known R11 non-closed gates:
- CSP remains report-only;
- full database snapshot/restore is not implemented;
- `platform_super` is not yet migrated to an explicit-role-only policy.

A green CI result proves repository consistency; it does not by itself prove the target hosting environment.


## R12 access/CSP cutover gates
Before switching CSP from report-only to enforce, capture a browser E2E run of the production admin shell, Vite assets, Luma components, Builder, Settings and Security pages with zero blocking CSP violations.

Before setting `platform_super=false`, Security Center must report zero implicit-only platform accounts and at least one explicit super account. The current administrator session must then be revalidated in the target environment after the configuration change. NanoPino intentionally does not auto-grant a wildcard role as part of this transition.


## R13 PHP runtime gate
Repository validation now includes an executable PHP suite across PHP 8.2, 8.3, 8.4 and 8.5. The release verification job also runs the same suite after full payload lint.

This closes the previous “PHP lint only” gap for framework-independent NanoPino domain behavior. It does not replace controlled Pinoox/database integration, signed PINX lifecycle testing or browser E2E; those remain Stable release gates.


## R14 native PINX lifecycle gate
Repository CI now requires a clean Pinoox + MySQL lifecycle after source verification. A candidate cannot pass the repository gate unless native platform installation, PINX build, fresh install, force-update and uninstall all complete successfully and the CMS table count remains stable across update and returns to zero after uninstall.

This materially closes the previous “no real Pinoox/DB package lifecycle” gap for unsigned packages in CI. Signed trust-chain validation, fault-injected recovery and target-host/browser E2E remain separate Stable gates.


## R15 installability and versioned-upgrade gate

R15 supersedes the R14 same-version force-update probe with a versioned native lifecycle. CI now installs the previous official NanoPino 0.23.28 release and upgrades it to 0.23.29 **without force**, then performs a separate clean 0.23.29 fresh install/uninstall cycle.

The lifecycle matrix runs against:
- Pincore 3.10.0 / native version code 216 — the declared minimum supported kernel;
- Pincore 3.14.4 — the current verified baseline;
- MySQL 8.4.

Release packaging is additionally blocked unless the built PINX passes the R15 artifact audit. The audit verifies package/version/minpin parity, required runtime files, Vite/build evidence, release metadata hashes, safe archive paths, bounded resource use and absence of development-only Admin source/build files.

NanoPino also ships a read-only environment preflight as the first package migration. This runs before CMS schema-creating migrations and blocks unsupported PHP/Pincore/database/filesystem conditions.

R15 materially strengthens installability evidence but does not close signed PINX trust-chain validation, fault-injected database/filesystem recovery, authenticated browser/mobile/WCAG E2E or exact target shared-hosting validation.
