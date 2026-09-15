# Phase 8 — Classic Theme Final Review

Date: 2026-09-15

## Decision

Phase 8 is complete at the source/conversion boundary. The implementation is
safe for static review and Builder preview when `safe_to_use` is true. It is
not an approval to execute, install or activate an arbitrary WordPress Theme.

## Verified in source

- Classic and Hybrid themes are accepted; Block and Unknown themes fail closed.
- Root templates include the standard index, front page, home, single, page,
  archive, author, taxonomy, attachment, singular, date, search, 404,
  comments, header, footer and sidebar variants.
- Nested `template-parts/*.php` files are inspected within bounded scanner
  limits.
- PHP is read as text and stripped from the static input. No `include`,
  `require`, `eval`, WordPress bootstrap, plugin, hook or template function is
  invoked.
- Static HTML is converted to native section, heading, paragraph and button
  blocks. WordPress Block Markup embedded in Classic files reuses the Phase 3
  parser and can be validated against the native Block Registry.
- Template tags generate machine-readable native binding suggestions for
  content, media, navigation and pagination sources.
- Unsupported findings contain code, category, severity, path, occurrence
  count and an adapter recommendation.
- Active script, iframe, object, embed and form markup are blockers. Scanner
  blockers and conversion blockers are preserved in the final report.
- `safe_to_use` remains distinct from activation readiness; warnings still
  require adapter and visual review.

## Evidence

- `php_lint=865/865`
- `php_tests=62 pass=62 fail=0`
- `frontend_tests=235 pass=235 fail=0`
- Source/dist parity: PASS
- Runtime parity: PASS
- Production assets checked: 62
- Source verification: PASS
- Final local commit: `03950c5`

## Remaining before production activation

1. Process-level sandboxing for a separate OS/container worker. The current
   worker is a strict static no-execution boundary inside the application and
   deliberately does not claim OS process isolation.
2. Explicit adapters for WordPress template tags, hooks, shortcodes, plugin
   dependencies, widgets, menus, comments, forms and dynamic blocks.
3. Full static media import and mapping to the native media library; image
   markup currently produces a finding and optional alt-text fallback.
4. Phase 7 asset publishing/enqueue integration with trust, cache invalidation
   and rollback; the asset pipeline currently only creates an inspection
   manifest.
5. Archive-to-worker orchestration: signed intake is implemented, but a real
   deployment flow must connect an approved extracted directory to the worker
   without bypassing archive safety or provenance checks.
6. Builder approval, persistence, preview parity and activation rollback for
   converted Classic output.
7. Fixture corpus from representative Classic, Hybrid and plugin-dependent
   themes, followed by browser visual, RTL, WCAG and performance verification.
8. Target/live installation, canary observation, signed release artifact and
   GitHub publication after the compatibility roadmap is complete.
