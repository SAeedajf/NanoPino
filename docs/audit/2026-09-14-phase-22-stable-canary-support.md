# Phase 22 — Stable, Canary and Post-Release Support

## Scope

Phase 22 turns the Phase 21 Release Candidate boundary into an explicit,
fail-closed promotion contract. It defines two release channels, a bounded
Canary health window, explicit Stable approval, rollback readiness and a
post-release support checklist. The implementation is decision-only: it does
not install, deploy, migrate or change the target environment.

## Implemented source contract

| Area | Implementation | Result |
|---|---|---|
| Release channels | `Cms/Release/ReleaseChannel.php` | `canary` and `stable` are explicit enum values |
| Canary evidence | `Cms/Release/CanaryHealthSnapshot.php` | Validates bounded samples, errors, availability, latency and incidents |
| Promotion gate | `Cms/Release/ReleasePromotionPolicy.php` | Fail-closed signature, archive, security, health and approval checks |
| Decision envelope | `Cms/Release/ReleasePromotionDecision.php` | Stable machine-readable decision with blocker codes and evidence |
| Support readiness | `Cms/Release/ReleaseSupportPlan.php` | On-call, monitoring, incident, rollback and customer communication gates |
| Operational bundle | `tools/live/monitor-nanopino-hypercare.sh`, `resources/release/hypercare-activation-phase22-v1.json` | GET-only target monitor, JSONL snapshots and explicit activation blockers |
| Canary decision record | `resources/release/canary-observation-phase22-v1.json`, `audit/2026-09-14-phase-22-canary-observation.md` | 20-sample read-only observation recorded; promotion remains blocked by deployment boundary and p95 latency |

Default Canary thresholds are a minimum five-minute window, 20 samples, no
more than 1% errors, at least 99% availability, p95 latency at or below
1200ms, zero critical incidents and passing health checks. Both channels
require a signed artifact, verified archive integrity, zero high-risk
findings and a ready support plan. Stable additionally requires explicit
Canary promotion, a closed observation window and explicit Stable approval.

## Verification

- Frontend contract suite: **228/228 PASS**.
- PHP runtime suite: **76/76 PASS**.
- Phase 22 source and machine-contract checks: **PASS**.
- Hypercare activation artifact is built from the current source: `com_pinoox_cms`,
  `0.23.72 / code 2372`, cryptographically signed with the local Ed25519
  publisher key `nanopino-local:main`. Target trust-store registration and
  signed lifecycle execution remain pending. The archive contains 972 entries
  / 970 payload entries. The final artifact SHA-256 is captured externally at
  build time because PINX signing metadata is intentionally timestamped.
- PINX preflight: **PASS**, zero High findings and two non-blocking warnings
  (`php.dynamic_include` and `php.raw_network`) already present in the
  security boundary.
- Target read-only smoke remains: public `/qwe/site` `HTTP 200`; private
  `/qwe/` without a session `HTTP 401`; no target mutation was executed.

## Phase 22 operational activation pass

The source-side support bundle is now **READY**: the monitor performs only
GET requests against the known Manager/public/private boundary, verifies the
required public security headers, emits JSONL snapshots and returns a
non-zero status when a snapshot fails. The incident, rollback and hypercare
close-out criteria are documented in `docs/deployment/hypercare-activation.md`.

Real production support activation remains **PENDING**. No named on-call
owner, scheduler/alert destination, authenticated target monitor, Canary
window or target mutation was available in this workspace. Therefore
`post_release_support_ready` remains false and no Stable claim is made.

The bounded read-only observation later recorded 20/20 successful samples and
100% availability, but probe p95 latency was `1992.764ms` against the
`1200ms` policy limit. The signed candidate was also not installed or
traffic-sliced on the target. Consequently Canary promotion and explicit
Stable approval remain **BLOCKED**.

## Current promotion status

The current candidate is **not promotable to Canary or Stable** because target
trust-chain registration, lifecycle and support evidence are incomplete. The
local signature is verified, but it is not evidence that the target trusts the
publisher or that a target install/update/rollback was executed. No claim is
made that the target is Stable, authenticated, migrated, backed up or ready
for customer traffic.

Machine-readable evidence: `resources/release/stable-canary-support-phase22-v1.json`.
