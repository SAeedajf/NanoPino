# Phase 8 — Release Candidate to Stable Gate

## Result

NanoPino was advanced from the 0.23.71 source package to release candidate
**0.23.72 / code 2372** using the native Pinoox release command. The candidate
PINX was built from the current source tree and inspected with the native PINX
info command.

This phase does **not** declare Stable. The repository gates are exercised here;
target-host, signed-package, authenticated-browser, and production-observability
evidence remain separate release gates.

## Verified in this checkout

- Native release command bumped `app.php` monotonically to `0.23.72 / 2372`.
- Native PINX metadata reports package `com_pinoox_cms`, minimum Pinoox `232`,
  and the expected candidate version.
- Admin Vite production build, source fingerprint parity, and direct-runtime
  fingerprint parity are verified.
- JavaScript regression/contract suite: **189/189 PASS**.
- PHP CMS runtime contract suite: **64/64 PASS**.
- PHP syntax checks and language-catalog parity checks pass.
- Release metadata, frontend manifest, source tree, and runtime evidence are
  synchronized after the candidate source update.
- Local Pinoox runtime recheck after the four additive index migrations reports
  `healthy: true`, score `100`, and `24/24` migrations complete with `0 pending`.
- The existing 0.23.71 filesystem-recovery artifact was preserved.

## Candidate limitations

The produced candidate is **unsigned**. No signing key or signed trust-chain
verification was available in this checkout, so signed install/update/uninstall
cannot be marked verified.

The local HTTP probes for `/nano`, `/nano/api/v1/cms/system/health`, `/nano/site`,
and alternate local ports returned **404**. Therefore authenticated browser E2E,
target Pinoox integration, database migration, theme activation, Safe Mode boot
order, deployment health, production security, and performance evidence are not
claimed.

## Stable blockers

The documentation manifest intentionally keeps these ten unresolved blockers:

1. Target shared-hosting Pinoox integration E2E
2. Production Luma/Vite browser and WCAG E2E
3. Signed PINX lifecycle E2E
4. Target database migration evidence
5. Persistent native Theme activation integration
6. Safe Mode real boot-order integration
7. Deployment health probes
8. Production security verification
9. Production performance evidence
10. Admin i18n public contract

Stable can be declared only after each blocker has target-appropriate evidence,
the signed artifact passes the native trust chain, and the production release
gate reports zero declared blockers.

## Umbrella test boundary

The repository-specific NanoPino gates above are green. The umbrella Pinoox
`composer test` and `composer test:apps` commands were also audited, but their
current vendor suites are not green in this checkout: failures include missing
generic DevDB fixtures (`users`, `posts`, `events`) and server tests dependent
on local temp/port conditions. These upstream/environment failures are not
counted as NanoPino contract-suite passes and do not justify a Stable claim.
