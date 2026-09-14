# Phase 15 — Builder, Theme and Public Rendering

## Outcome

Phase 15 is source-verified and locally integrated. The canonical Builder,
native Pinoox Theme stack and public rendering boundary now share an explicit
contract: Builder and Theme mutations invalidate public output, while public
pages resolve the active native theme and consume only validated design tokens.

The public boundary continues to use the safe built-in renderer and the
sanitized Builder block renderer. It does not evaluate arbitrary theme
template source from a public request. This keeps the current release
backward-compatible and avoids turning a theme file into an executable public
input without a separate native template-runtime contract.

## Builder and cache coherence

- Builder create, save, publish and restore operations invalidate the affected
  site's public render cache after durable mutation and audit completion.
- Theme activation invalidates the affected site's public and theme cache tags.
- Site-scoped `theme.design.overrides` set/reset operations invalidate the same
  public boundary.
- Invalidation remains best-effort: a cache failure cannot undo a successful
  durable mutation; the next public request safely repopulates the cache.

## Theme and public rendering

- `CmsRuntimeServices::publicThemeView()` resolves the active native
  `com_pinoox_cms` theme through `ThemeEngine` and the native inheritance stack.
- Site design overrides are read from the existing settings repository and
  passed through `DesignSchemaValidator` before entering rendering.
- Home, page and taxonomy renderers compile scalar design tokens into bounded
  CSS custom properties and consume semantic colors, typography and radius
  variables with safe fallbacks.
- Published Builder content remains available on public content pages through
  the existing `BuilderPublishedResolver` and safe block rendering boundary.

## UX and safety checks

The public surfaces retain semantic landmarks, keyboard-visible focus states,
44px Builder button affordances, responsive layout behavior and reduced-motion
handling. Public failures continue to fail closed to a safe fallback or a
controlled unavailable response; malformed theme metadata does not expose
template or filesystem details.

## Verification

- PHP CMS runtime suite: **69/69 PASS**.
- Frontend contract suite: **203/203 PASS**.
- Production Vite build: **PASS**, 62 reachable assets and source/runtime
  parity verified.
- Documentation audit: **PASS** after recording this phase and its evidence.
- Target `test.boxpdf.ir`: read-only boundary only; no Builder save, theme
  activation, design mutation, cache purge, deployment or migration executed.

## Release decision

The source candidate remains **0.23.72 / code 2372**. Phase 15 is complete in
the local source boundary. Stable release remains blocked by target deployment
and authenticated E2E, signed PINX lifecycle, target database/recovery,
production security/performance and browser/WCAG evidence requirements.
