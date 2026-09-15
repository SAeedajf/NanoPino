# Testing Strategy

Repository CI is a merge/release gate, not a substitute for target-runtime E2E. The dedicated NanoPino quality workflow runs on CMS-affecting push and pull requests; the platform archive workflow remains release-oriented.

Current CI has two layers:

1. **PHP runtime contract suite**:
   - executes NanoPino PHP classes through the discoverable `composer test:cms` command (backed by repository-level `tests/php/run.php`);
   - covers the checked-in manifest, unsafe extension paths, deterministic package planning, API response envelopes, runtime route safety metadata, capability registration and single-site authorization behavior.
2. **Primary release verification** (the workflow in this checkout currently uses PHP 8.3 and Node 24; older PHP/Node matrix claims are historical until a matrix workflow is present):
   - locked `npm ci`;
   - production admin build;
   - full PHP lint;
   - executable PHP runtime suite;
   - Node contract/regression suite;
   - source/runtime/dist parity checks;
   - verified admin artifact upload.

The PHP harness is intentionally dependency-light and lives outside the PINX payload. It exercises actual PHP behavior rather than source-text assertions.

Still open for Stable evidence:
- the runtime suite currently covers package/API contracts; it does not replace full CMS domain, security-driver and database integration coverage;
- target-host Pinoox/database lifecycle and migration evidence;
- authenticated API/permission tests with real users/roles;
- signed PINX fresh-install/update/uninstall/fault-injection lifecycle in CI is now covered with an ephemeral trusted publisher key; target trust-chain continuity and target rollback rehearsal remain open;
- browser/mobile/accessibility E2E;
- target-host performance/security probes.

A green repository CI proves source/runtime consistency and executable PHP domain behavior. It does not prove the target hosting environment.


## R14 Pinoox lifecycle integration
The current `validate.yml` workflow provisions a pinned Pinoox runtime and MySQL
8.4 service. It runs native platform setup, builds the package from the verified
production dist, and exercises fresh install, versioned update, uninstall and
fault-injected recovery on Pincore 3.14.0 and 3.14.4.

In the historical R14 workflow, after the PHP compatibility matrix and primary source verification passed, CI provisioned a clean MySQL 8.4 service and a pinned Pinoox runtime. It then:
1. installs Pinoox non-interactively through the native installer;
2. restores the verified NanoPino admin dist produced by the primary build;
3. builds NanoPino through the native `pinx:build` path;
4. fresh-installs the generated PINX;
5. validates installed package/version metadata and the minimum CMS table count;
6. force-installs the same package again to exercise the update path and checks table-count stability;
7. uninstalls through native `pinx:uninstall` and requires both the app directory and CMS tables to be removed.

The Pinoox project checkout is pinned to a known commit for repeatability. Composer reports the resolved Pincore version in the job log. The lifecycle
now creates an ephemeral Ed25519 publisher key, registers its public key in the
ephemeral Pinoox trust configuration, signs every test package through the
canonical helper and sets `require_signature=true` before native installation.

This is a real signed Pinoox/MySQL package lifecycle test in CI. It still does
not cover target-host trust-store continuity, authenticated browser workflows,
or production hosting behavior.
