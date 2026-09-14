# NanoPino Admin Design System

Phase 2 establishes a semantic UI contract for the native Vue/Luma Admin. Page
components should consume the `--cms-*` tokens below, while Luma and PrimeVue
continue to receive their compatibility values through the `--px-*` and `--p-*`
bridges in `theme/cms-admin/src/styles/admin.scss`.

## Foundations

- Direction: explicit RTL from the CMS boot payload; LTR remains supported by
  the same logical CSS properties.
- Font: Vazir/Vazirmatn with system fallback; base size is 16px and body line
  height is 1.6.
- Primary: blue (`--cms-primary`) for navigation, actions and focus; green,
  amber and red are reserved for success, warning and danger states.
- Surfaces: canvas, panel and muted panel are semantic and switch with
  `data-theme="dark"`.
- Spacing: `--cms-space-1` through `--cms-space-12`, based on a 4px unit.
- Interaction: controls use a minimum 44px block size, visible keyboard focus,
  `touch-action: manipulation`, and reduced-motion fallbacks.

## Shell contract

Luma owns the runtime shell: `RootShell` mounts global loading, toast and
confirmation hosts; `PageLayout` mounts the sidebar, topbar, mobile navigation
and semantic `<main>`. NanoPino configures it through `buildThemeConfig()` with
the manifest-driven navigation, 272px desktop rail, 64px topbar and 1440px
content ceiling.

The boot layer adds a localized skip link to the rendered main region. The link
is intentionally installed after router mount because the `<main>` element is
owned by Luma's `PageLayout`.

## Extension guidance

Admin extensions must use semantic tokens and Luma primitives where possible.
Do not introduce page-wide raw colors, fixed left/right offsets, hover-only
actions, or controls smaller than 44px. Use logical properties (`margin-inline`,
`inset-inline`, `border-block`) so the same component remains correct in RTL and
LTR. All asynchronous states must expose a visible status or alert and a
recoverable action.
