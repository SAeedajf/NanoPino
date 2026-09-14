# NanoPino Project Status

Current package: **0.23.72 / code 2372**.

**Documentation Freeze:** current 0.23.72 candidate; R15 installability evidence is historical and Stable 1.0 is not declared.

**Installable RC:** [v0.23.72-rc1](https://github.com/SAeedajf/NanoPino/releases/tag/v0.23.72-rc1) points to the verified, locally signed PINX artifact built from the green `main` commit. Install through the native Pinoox PINX installer; do not extract it manually.

**Architecture audit:** the 2026-09-10 full audit classifies the package as Beta / functional but immature. It adds a repository-level PHP runtime contract gate and records the remaining database, security, target-runtime and browser evidence gaps. See `docs/audit/2026-09-10-full-architecture-audit.md`.

**Latest release operations status:** Phase 22 adds a fail-closed Canary/Stable promotion policy and post-release support readiness contract. A 20-sample read-only target observation recorded 100% availability and 0% errors, but probe p95 latency was 1992.764ms against the 1200ms limit; the signed candidate was not installed or traffic-sliced on target. Canary promotion and explicit Stable approval remain pending. See `docs/audit/2026-09-14-phase-22-canary-observation.md` and `docs/audit/2026-09-14-phase-22-stable-canary-support.md`.

**Latest development phase:** Capability selection and implementation added a validated site-scoped public navigation menu by upgrading the existing Settings and PublicSite rendering layers. See `docs/audit/2026-09-11-capability-selection-public-navigation.md`.

**Canonical phase map:** the current product phases 1–21 and their evidence boundaries are recorded in `docs/audit/2026-09-12-phase-matrix.md`.

**Latest Final Release Candidate pass:** Phase 22 records the current signed
0.23.72 artifact and the final local evidence: 228/228 frontend tests, 76/76
PHP tests, documentation/metadata/parity checks, source/runtime verification,
and a read-only target smoke. Stable remains blocked by target deployment,
authenticated lifecycle, performance and operational gates. See
`docs/audit/2026-09-14-phase-22-stable-canary-support.md`.

**Latest Security Verification/Threat Model pass:** Phase 20 records five
trust boundaries and an 11-threat STRIDE-oriented register. Source controls,
the current PINX preflight and target read-only security headers were verified;
authenticated target security E2E, signed publisher lifecycle, penetration
testing and production operational evidence remain open. See
`docs/audit/2026-09-14-phase-20-security-verification-threat-model.md`.

**Latest Performance/Scalability pass:** Phase 19 makes the canonical Content
collection request the scalar list projection, batches scheduled-publication
fresh reads and detail hydration after compare-and-set claims, and adds a
build-enforced JavaScript/CSS budget. Local contract, PHP, build, source/dist
parity and budget checks pass. Target authenticated latency, concurrency,
OPcache, database, cache and queue measurements remain pending. See
`docs/audit/2026-09-14-phase-19-performance-scalability.md`.

**Latest UI/UX and browser pass:** Phase 18 adds the shared 44px interaction
baseline, preserves zoom and text scaling, moves focus to the new main content
after SPA navigation, and verifies the local Vite Admin harness in Chromium 145
and Firefox 147 at mobile, tablet and desktop sizes. The target public site was
smoke-tested read-only; `/qwe/` still requires an authenticated session, so
target Admin accessibility and authenticated browser E2E remain pending. See
`docs/audit/2026-09-14-phase-18-uiux-wcag-browser-matrix.md`.

**Latest Recovery/Safe Mode pass:** Phase 17 makes malformed Safe Mode state
fail closed, permits only explicitly core packages during Safe Mode, escalates
direct restore failures into persisted Safe Mode, and adds deterministic
test-only fault checkpoints across transactional and native recovery paths.
Local recovery and fault-injection verification passed; target boot-order and
authorized native fault-injection rehearsal remain pending. See
`docs/audit/2026-09-14-phase-17-recovery-safe-mode-fault-injection.md`.

**Latest signed Plugin/Extension Lifecycle pass:** Phase 16 makes valid PINX
cryptographic signatures mandatory at the install/update execution boundary.
Review tickets remain digest-bound and one-time; permissions, publisher
changes, static-risk findings and recovery/Safe Mode controls remain enforced
around the native Pinoox lifecycle. Local source verification passed; the
authorized target signed install/update/uninstall rehearsal remains pending.
See `docs/audit/2026-09-14-phase-16-signed-plugin-extension-lifecycle.md`.

**Latest Builder/Theme/Public Rendering pass:** Phase 15 connects the native
active Theme stack and validated site design overrides to the safe public home,
page and taxonomy renderers. Builder and Theme mutations now invalidate public
render output so Preview/Publish changes do not remain stale. Local source and
contract verification passed; arbitrary theme template execution and target
Builder/Theme E2E remain intentionally unverified. See
`docs/audit/2026-09-14-phase-15-builder-theme-public-rendering.md`.

**Latest infrastructure pass:** Phase 14 connects the durable Queue runtime to
the native Pinoox Scheduler, adds content-driven semantic cache invalidation,
enables short-lived site/page caching and exposes the native Storage boundary.
Media upload/delete safety and scoped storage keys remain enforced. Local
source contracts pass; target disk/cache/cron/media execution remains pending.
See `docs/audit/2026-09-14-phase-14-media-storage-cache-queue.md`.

**Latest Content/Editorial Production pass:** scheduled content now has a
real native Pinoox Scheduler task. Due records are claimed with a database
compare-and-set transition, receive a Published revision and audit event, and
cannot be published twice by overlapping scheduler runs. The complete flow
passed on an isolated DevDB fixture; target editorial E2E remains pending.
See `docs/audit/2026-09-12-phase-13-content-editorial-production-workflow.md`.

**Latest Database Lifecycle pass:** the source now supports a real isolated
DevDB/SQLite migration lifecycle for local verification while retaining the
native MySQL/MariaDB production boundary. Fresh install, no-op update with a
sentinel row, and reset rollback passed; all owned CMS tables were removed by
reset while native platform tables remained. Target database migration was
not executed without backup/mutation authority. See
`docs/audit/2026-09-12-phase-12-database-lifecycle-migration.md`.

**Latest Auth/Session/Role pass:** source contracts pass the dedicated Phase 11
gate (`4/4`). The target private boundary rejects no-credential requests, but
authenticated role switching, session revocation and CSRF mutation remain
pending because the live browser transport could not recover the prior session;
the target also reports 403 `ACCESS_DENIED` where the current source contract
distinguishes anonymous requests as 401 `AUTHENTICATION_REQUIRED`. See
`docs/audit/2026-09-12-phase-11-auth-session-role-e2e.md`.

**Phase 9:** `app.php` and `manifest.json` now share the canonical 89-entry
service registry in `resources/release/service-registry-parity-v1.json`; core
Admin route components are checked for missing/orphan entries and legacy
Dashboard sources are explicitly classified.

**Latest release pass:** Phase 8 produced the **0.23.72 / code 2372 Release Candidate**. Technical repository checks pass; the Release Gate remains withheld until the declared target-environment, signed-package, browser/accessibility, recovery, security, performance, and i18n blockers are closed. See `docs/audit/2026-09-12-phase-8-release-candidate.md`.

**Latest live-environment pass:** Phase 10 verified the authenticated Manager,
the `/qwe/` NanoPino mount, the public `/qwe/site` route, and real Health/API
responses on `test.boxpdf.ir`. The target currently runs `0.23.68`; the local
`0.23.72` candidate update remains pending after the browser file-chooser
transport was interrupted. See `docs/audit/2026-09-12-phase-10-real-pinoox-test-environment.md`.

NanoPino is a Pinoox CMS and extension platform with a Vue/Luma admin, runtime API, content/media/settings/users, themes, visual builder, extension lifecycle, recovery, search, queue/cache/storage, health, logging and performance services. The Developer SDK now exposes seven executable starter paths and a safe local generator.

## Historical repository gates before R15
- Production Admin build is required before source verification.
- PHP runtime tests execute across PHP 8.2–8.5.
- Node contract/regression tests must pass.
- Public runtime routes are checked bidirectionally against machine-readable API contracts.
- Generated runtime/source parity is verified.
- Native Pinoox/MySQL PINX build/install/update/uninstall lifecycle is exercised in CI.

## Historical R15 candidate gates
- Native minimum Pincore is raised to 3.14.0 / code 232.
- PINX artifact structure and release metadata are audited after native build.
- A read-only installability preflight runs before CMS schema-creating migrations.
- CI exercises both Pincore 3.14.0 and 3.14.4.
- The native update path is 0.23.28 → 0.23.29 without force.
- A separate clean 0.23.29 install/uninstall cycle is required.
- CMS table count must remain stable on update and return to zero after uninstall.

These R15 items become verified release evidence only after the R15 branch workflow is green.

## Release gates still requiring target-environment evidence
- Pinoox integration E2E on the exact target shared-hosting runtime.
- Browser/mobile/WCAG validation of the production Luma/Vite build.
- Authenticated target Admin browser matrix and assistive-technology audit;
  local Chromium/Firefox source-harness coverage is now recorded by Phase 18.
- Signed PINX install/update/uninstall trust-chain E2E.
- Target database migration evidence and hosting privilege validation.
- Persistent native theme activation.
- Safe Mode real boot-order integration.
- Fault-injected filesystem/database recovery on the target runtime; local recovery and fault-injection contracts are now covered by 72/72 internal PHP tests.
- Production security/performance probes and authenticated target load evidence;
  Phase 19 only verifies source-local performance controls and budgets.

## Security status
Remote Search is fail-closed by default and uses explicit configuration plus an HTTPS SSRF-guarded transport when enabled. CSP is enforced by default; an explicit `PINOOX_CMS_CSP_MODE=report-only` value is available as a rollback while diagnosing a compatibility issue. `platform_super` is disabled for NanoPino; explicit `admin`/`superadmin` roles are required. The security center distinguishes completed explicit-role cutover from pre-cutover readiness.
