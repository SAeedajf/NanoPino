# NanoShell Performance Improvement Audit

Date: 2026-09-15

Status: local source and production bundle verification complete; target
telemetry and real-browser timing remain separate release gates.

## Implemented optimization

The initial Admin entry graph no longer imports the Luma RootShell, PageLayout
or Extension Host UI barrel synchronously. The same components remain available
through Vue async components and direct per-component loaders. Route and host
contracts are unchanged; Luma visual dependencies are fetched when the shell,
route or extension actually needs them.

The performance budget now measures both the complete emitted bundle and the
initial JavaScript graph from `dist/.vite/manifest.json`. This prevents a
future code-splitting regression from being hidden by a passing total-bundle
budget.

## Measured result

| Metric | Before | After | Change |
|---|---:|---:|---:|
| Initial JavaScript | 1,595,196 B | 1,073,738 B | -32.7% |
| Initial CSS | 197,939 B | 160,825 B | -18.7% |
| Total JavaScript | 1,876,491 B | 1,879,748 B | +0.2% |
| Total CSS | 221,991 B | 221,991 B | 0.0% |

The small total JavaScript increase is the async-loader/runtime overhead; the
large shared UI and icon dependencies moved out of the first paint graph.
The resulting initial JavaScript remains below the new `1,200,000` byte gate.

## Verification

- Frontend tests: `238/238` pass.
- Production build: PASS.
- Initial JS budget: `1,073,738 / 1,200,000` bytes.
- Total JS budget: `1,879,748 / 2,200,000` bytes.
- Total CSS budget: `221,991 / 300,000` bytes.
- Largest JS: `678,167 / 750,000` bytes.
- Largest CSS: `126,609 / 150,000` bytes.
- Source/dist parity: PASS; source fingerprint has 51 files.
- Runtime parity: PASS; runtime fingerprint has 17 files.
- Production assets: `62` verified; root-domain asset references: `0`.

This is a source/build result, not a claim about real target TTFB, browser LCP,
mobile network behavior or production server capacity. Those require a target
deployment and browser matrix run.
