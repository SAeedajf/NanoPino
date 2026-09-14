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
- controlled Pinoox + database integration tests;
- authenticated API/permission tests with real users/roles;
- signed PINX fresh-install/update/uninstall/rollback lifecycle;
- browser/mobile/accessibility E2E;
- target-host performance/security probes.

A green repository CI proves source/runtime consistency and executable PHP domain behavior. It does not prove the target hosting environment.


## R14 Pinoox lifecycle integration
> **Historical branch evidence:** this section describes the R14 lifecycle
> workflow and is not executed by the current `nanopino-quality.yml` checkout.
> The current workflow covers the PHP/frontend source gate; native Pinoox and
> MySQL lifecycle evidence remains an open Stable requirement.

In the historical R14 workflow, after the PHP compatibility matrix and primary source verification passed, CI provisioned a clean MySQL 8.4 service and a pinned Pinoox runtime. It then:
1. installs Pinoox non-interactively through the native installer;
2. restores the verified NanoPino admin dist produced by the primary build;
3. builds NanoPino through the native `pinx:build` path;
4. fresh-installs the generated PINX;
5. validates installed package/version metadata and the minimum CMS table count;
6. force-installs the same package again to exercise the update path and checks table-count stability;
7. uninstalls through native `pinx:uninstall` and requires both the app directory and CMS tables to be removed.

The Pinoox project checkout is pinned to a known commit for repeatability. Composer reports the resolved Pincore version in the job log.

This is a real Pinoox/MySQL package lifecycle test. It still does not cover signed-package trust, authenticated browser workflows, fault-injected rollback or production hosting behavior.
