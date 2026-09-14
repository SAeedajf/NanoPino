# Phase 18 — UI/UX, WCAG and Browser Matrix

## Outcome

Phase 18 hardens the shared NanoPino Admin interaction boundary and records a
reproducible browser matrix. The work is source-local and backward-compatible:
it does not change the CMS data model, API envelopes, permissions, or target
installation state.

## Changes

- Kept the server shell zoomable with a standards-compliant viewport and
  preserved browser text scaling.
- Added a shared 44px block-size baseline for native buttons and custom
  `role="button"` controls. Existing inputs, selects and textareas already use
  the same 44px touch token.
- Moved keyboard focus to `#cms-main-content` after SPA route navigation while
  using `preventScroll` so route changes do not unexpectedly reposition the
  user. The localized skip link remains available before the navigation rail.
- Marked the Safe Mode shield icon as decorative; its status remains conveyed
  by the visible title, message and live region.
- Raised the semantic status foregrounds used by compact Dashboard and state
  surfaces so success, warning and danger text remains suitable for WCAG AA
  contrast in both light and dark themes.
- Preserved RTL/LTR document direction, semantic loading/error/empty states,
  visible focus rings, forced-colors focus behavior and reduced-motion rules.
- Used the UI/UX design-system guidance for WCAG AA+, 44px touch targets,
  keyboard order and responsive viewports. No new visual framework or ad-hoc
  page-specific CSS was introduced.

## Verification

| Check | Result | Evidence |
|---|---|---|
| Frontend contract suite | PASS — 208/208 | `theme/cms-admin/tests/*.test.js` |
| PHP CMS runtime suite | PASS — 72/72 | `tests/php/run.php` |
| Vite production build | PASS | 62 reachable assets; source/dist parity verified |
| Chromium 145 local Admin harness | PASS | 375×812, 768×1024, 1440×900; DOM boot and screenshots |
| Firefox 147 local Admin harness | PASS | 375×812, 768×1024, 1440×900; screenshots |
| Chromium 145 target public smoke | PASS | read-only `https://test.boxpdf.ir/qwe/site` |
| Firefox 147 target public smoke | PASS | read-only `https://test.boxpdf.ir/qwe/site` |

The local browser harness exercises the built Vue/Luma Admin boot path with a
minimal server boot payload. It is not a substitute for an authenticated
target session or a screen-reader audit.

## Target boundary

- `https://test.boxpdf.ir/manager/` was inspected read-only.
- `https://test.boxpdf.ir/qwe/` returns `401` without an authenticated session;
  no credentials, cookies, CSRF token or target state were changed.
- `https://test.boxpdf.ir/qwe/site` is reachable and was used for a read-only
  public browser smoke test.
- Authenticated Admin interaction, axe/Accessibility Insights, real keyboard
  traversal with a screen reader, Safari/Edge engines, and target deployment
  regression remain pending.

## Gate

Phase 18 is **source and local browser verified**, but it does not make the
release Stable. The production gate remains blocked until the authenticated
target matrix, target update/lifecycle, signed trust chain, database/recovery,
security/performance and assistive-technology evidence are completed.

Machine-readable evidence: `resources/release/uiux-wcag-browser-matrix-phase18-v1.json`.
