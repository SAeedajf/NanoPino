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


## P1 implementation notes

P1 replaces daily authoring fields that exposed numeric IDs or infrastructure terminology with Registry-driven controls.

- RichText fields render an author-facing formatting surface with links, lists and Media Library image insertion. Paste is reduced to plain text and rendered HTML is allow-listed before entering the editable surface.
- Media/Gallery fields use the existing Media API; Relation and Parent controls use paged Content list/read APIs; Taxonomy fields use the registered Taxonomy service through the versioned term-search route.
- Core Post categories and tags are ordinary registered Taxonomy fields, so extensions can use the same Field/Taxonomy contract without changing the Content page.
- Revision selection is independently searchable and paginated; a requested content ID can be resolved directly instead of being restricted to the first 100 records.
- Revision restore is preceded by an integrity-verified snapshot preview and a current-vs-revision diff.
- Media upload failures can be retried or removed per file, and zero-result pagination no longer fabricates a 1-to-0 range.

P1 closed on 2026-09-07 after Admin build/runtime parity, API-contract verification, behavior tests, PHP 8.2–8.5 and PINX lifecycle gates on Pincore 3.14.0/3.14.4 passed; PR #10 was merged.


## P2 implementation notes

P2 keeps Builder/Theme/Block behavior on existing Pinoox-native contracts and removes ambiguity from the admin workflows.

- Builder target selection is author-facing: content, template, template part and whole-site targets use named selectors; raw target keys are secondary technical detail.
- Opening an existing Builder document is read-only with respect to creation. Creating a new document is a separate explicit action using the create route. A document target is locked while open and dirty close requires confirmation.
- The Block Library is unavailable until a concrete document is open, so the not-open state no longer instructs users to perform disabled editing actions.
- Primary Builder controls and viewport names are localized; status uses the localized not-open state.
- Pinoox theme activation remains per package. Theme Center identifies the NanoPino site package separately from other application theme stacks and exposes Builder/Site Editor actions only for the site-package active theme.
- Theme cards use supplied cover previews and an explicit no-preview state when a theme provides no cover.
- Block Catalog adds searchable previews, capability badges and a direct Block Pack installation path. The Core remains small; missing image/gallery/video/navigation/form capability can be supplied by any registered third-party namespace.
- Global Styles preview now reflects base font size as well as colors, fonts, spacing, radius and content width.
- Responsive Contract is shown only when breakpoints exist and explains their fallback role.
- Decorative sample controls are visibly marked as preview-only.

P2 remains open until regression tests, production Admin build/runtime parity, PHP matrix and PINX lifecycle gates pass.

## P4a — audit investigation presentation

This follow-up is independent of the open P3 PR #12. P0/P1/P2 implementation is
already on main; it does not reimplement their work or claim target-host closure.

Audit findings #54 and #55 are partially addressed:
- Desktop and mobile audit views show the event's Unix-seconds timestamp with an
  explicit UTC display and a machine-readable ISO time. Missing/invalid values
  remain unknown rather than becoming the Unix epoch or the current time.
- Search has a native label and now includes actor ID, scope, event ID and UTC
  date in addition to action, owner, target and correlation.
- Column labels have Persian/English translations. Actor names and translated
  action descriptions remain pending; no identity data is invented.

Validation: 121 Node tests passed (including timestamp/search behavior), production
Vite build passed, source/dist parity passed, 56 emitted assets verified.
PHP lint and target-host browser checks were not run for this follow-up. No
migration, deployment or PINX version increment is included.

Remaining sequence: finish/review P3 -> P4 identity and observability integration
-> P5 accessibility/mobile -> P6 target-host and native lifecycle verification.
P4a is a reviewable slice, not completion of P4 or all 76 findings.

## P4b — truthful API responses and incident correlation (2026-09-08)

Source-confirmed follow-up to #58 and the P0 truthful-failure contract:

- A shared response decoder is used by Vue, directly served runtime and admin actions.
- Malformed/HTML/null/primitive HTTP 200 responses reject with `CMS_INVALID_RESPONSE` instead of acknowledging a save/upload or presenting an empty list. The message asks the user to check current state before retrying; mutations are never automatically retried.
- HTTP 204 and HEAD retain their valid empty-response semantics. Existing data envelopes, status, error code and detail payloads remain available to callers.
- Errors retain bounded error/correlation IDs; the user-visible message includes the server's tracking ID with bidi isolation. Diagnostic detail payloads and response HTML are never appended to the message.
- Eight behavior tests exercise all three adapters, malformed success, structured failures, HTTP 204, normal data and non-JSON HTTP failures. Full Admin suite: 129/129 passed.

This is a scoped implementation/test result, not closure of all P4 findings. Live failure injection, mobile/desktop rendering and the full role/health/infrastructure matrix remain unverified. The previous host upgrade to Pincore 3.14.4 and NanoPino 0.23.29 does not include this follow-up until its PR is reviewed and deployed.

### P4b packaging correction

The first PR #16 CI run failed both native lifecycle jobs because the regenerated
`payload/resources/release/admin-runtime-v1.json` was omitted from the commit.
The rebuilt dist therefore described 17 runtime modules while the committed
release manifest still described 16. The corrected manifest is included, and CI
now checks the committed runtime fingerprint before running any build that can
regenerate it. No integrity check is bypassed or weakened.
