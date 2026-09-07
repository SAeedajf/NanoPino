# NanoPino Live Admin Remediation Plan — 2026-09-07

Status: active remediation backlog  
Source baseline: live audit performed against NanoPino 0.23.28  
Repository baseline for implementation: 0.23.29 / code 2329

## Prioritization rules

1. Prevent data loss and false persistence first.
2. Make API failure states truthful before adding authoring features.
3. Repair extension/navigation contracts before cosmetic UI.
4. Complete authoring workflows before catalogs and operational polish.
5. Preserve Pinoox-native Contracts/Registries/RBAC/Pinx boundaries; no vendor/Pincore business-logic patches.
6. A finding is closed only with implementation, regression coverage, documentation, and target-runtime evidence where the finding was observed live.

## Phase map

| Phase | Priority | Audit IDs | Goal | Merge gate |
|---|---|---|---|---|
| P0 State integrity & truthful failures | Critical | 1-14 | Eliminate destructive editor races, stale responses, invalid package review state, misleading API failure UI, and mount-path navigation regression | Node behavior tests + production Admin build + repository verification |
| P1 Content/Media authoring | High | 15-28 | Replace technical-ID/raw-text workflows with author-facing rich text, media/relation/taxonomy selectors, scalable parent/revision selection, upload recovery and revision compare | Editor/API integration tests + mobile authoring checks |
| P2 Builder/Theme/Block UX | High | 29-40 | Make Builder onboarding, targets, block catalog, theme scope, preview and responsive controls operational and understandable | Builder E2E + responsive/mobile checks |
| P3 Extension/Update/Recovery UX | High | 41-50 | Human extension identity, safe core-module actions, row operation state, package review, update history, recovery/Safe Mode guidance and executable SDK starts | PINX lifecycle + recovery tests + extension E2E |
| P4 Identity/Audit/Health/Infrastructure | High/Medium | 51-62 | Complete user/RBAC handoff, actionable audit/security/health reporting, error correlation, queue/cache operations and real performance measurements | permission tests + observability integration |
| P5 Navigation/i18n/a11y/mobile | Medium | 63-76 | Search indexing, native links, account control, terminology/number/timezone consistency, labels, mobile media/builder interactions and intermediate-width layout | WCAG 2.2 AA keyboard/mobile/browser matrix |
| P6 Release verification | Release gate | all observed items | Re-run the live audit on target hosting, capture before/after evidence, classify any environment-only failures, and cut the next version only when High/Critical issues are closed | target-host E2E + PINX update/rollback + release notes |

## P0 implementation contract

P0 uses request snapshots and request-generation guards rather than globally freezing editors:

- a save acknowledges only the state that was submitted;
- edits made after request start remain dirty and visible;
- content scheduling first persists the submitted content snapshot, then schedules that record;
- media detail reads accept only the latest selection response;
- changing/closing media with an unsaved draft requires explicit consent;
- selecting a different extension package invalidates inspection, approval and any late inspection response;
- Full Site Editor treats Builder document loading and Design Token loading as independent states;
- statistics and design controls do not synthesize successful-looking values when their API has not loaded;
- content-type changes preserve common fields and require consent before dropping incompatible populated values.

## P0 baseline reconciliation

Some live-audit findings target 0.23.28 and must be reconciled with 0.23.29 before code changes:

- Audit #5: current 0.23.29 runtime navigation is mount-aware; no hard-coded `/qwe/appearance/extensions` path is present. Keep a regression check rather than reintroducing a second router.
- Audit #58: the current common API adapter already appends server `error_id`/category when provided. P4 still needs consistent user-facing correlation treatment across canonical Vue/API adapters.
- Audit #1/#3: the live HTTP 500 root cause is not inferred from UI symptoms. P0 fixes false success/empty presentation; backend closure requires a reproducible target-runtime trace/error ID.

## Definition of closure

A row can move to Done only when:

- source and directly-served runtime implementations remain aligned while both delivery paths exist;
- tests exercise the behavior, not only source-string presence;
- permission/security boundaries are unchanged or explicitly reviewed;
- no Pincore/vendor business logic is modified;
- documentation records any new public/extension contract;
- observed-live defects receive target-host verification before being called fully resolved.
