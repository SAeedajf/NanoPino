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
