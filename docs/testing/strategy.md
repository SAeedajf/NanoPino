# Testing Strategy

Repository CI is a release gate, not a substitute for target-runtime E2E.

Current CI has two layers:

1. **PHP runtime matrix** on PHP 8.2, 8.3, 8.4 and 8.5:
   - executes NanoPino PHP classes directly through `tests/php/run.php`;
   - covers CSP policy behavior, SSRF enforcement, Recovery Manager orchestration, Security Posture and package/release contracts.
2. **Primary release verification** on PHP 8.4 + Node 22:
   - locked `npm ci`;
   - production admin build;
   - full PHP lint;
   - executable PHP runtime suite;
   - Node contract/regression suite;
   - source/runtime/dist parity checks;
   - verified admin artifact upload.

The PHP harness is intentionally dependency-light and lives outside the PINX payload. It exercises actual PHP behavior rather than source-text assertions.

Still open for Stable evidence:
- controlled Pinoox + database integration tests;
- authenticated API/permission tests with real users/roles;
- signed PINX fresh-install/update/uninstall/rollback lifecycle;
- browser/mobile/accessibility E2E;
- target-host performance/security probes.

A green repository CI proves source/runtime consistency and executable PHP domain behavior. It does not prove the target hosting environment.


## R14 Pinoox lifecycle integration
After the PHP compatibility matrix and primary source verification pass, CI provisions a clean MySQL 8.4 service and a pinned Pinoox runtime. It then:
1. installs Pinoox non-interactively through the native installer;
2. restores the verified NanoPino admin dist produced by the primary build;
3. builds NanoPino through the native `pinx:build` path;
4. fresh-installs the generated PINX;
5. validates installed package/version metadata and the minimum CMS table count;
6. force-installs the same package again to exercise the update path and checks table-count stability;
7. uninstalls through native `pinx:uninstall` and requires both the app directory and CMS tables to be removed.

The Pinoox project checkout is pinned to a known commit for repeatability. Composer reports the resolved Pincore version in the job log.

This is a real Pinoox/MySQL package lifecycle test. It still does not cover signed-package trust, authenticated browser workflows, fault-injected rollback or production hosting behavior.
