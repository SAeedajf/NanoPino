# Live NanoPino 0.23.97 and theme-install QA

Date: 2026-09-15

Target: `https://test.boxpdf.ir`

## Release update

- Artifact: `NanoPino-0.23.97-theme-install-action-signed.pinx`
- Package: `com_pinoox_cms`
- Version: `0.23.97 #2397`
- SHA-256: `39f29d8181982e3eb3008de7f8784efd9fa34750564e72a513052575479afb4f`
- Signature: verified, key `nanopino-local:main`
- Native update operation: succeeded
- Recovery point: created before extraction
- Migration, lifecycle, runtime-cache rebuild: succeeded

## Theme installation

The original free `Twenty Nineteen` ZIP, version `3.4`, was selected from the
NanoPino Appearance page. Preview reported 19 converted templates and 13
detected assets. Installation completed with the static NanoShell conversion
message; source PHP was not executed.

The live Appearance page then reported:

- Installed themes: `21` (before: `20`)
- Compatible themes: `21` (before: `20`)
- Blocked themes: `0`
- New theme: `Twenty Nineteen`, `v3.4`, compatible, parent `twentynineteen`
- Active site theme: unchanged, `Twenty Seventeen v4.2`

The new theme was intentionally installed but not activated. Activation is a
separate administrator action and was not implied by the install request.

## Settings and public rendering

The live Full Site Editor exposed enabled controls for primary/background/text
colors, body and heading fonts, base text size, spacing, content width and
radius. The save control was disabled only because no edit was made during the
read-only verification.

The public site loaded with HTTP 200, title `NanoPino | NanoPino · سایت عمومی`,
zero admin-shell nodes, two public content links, the existing global design
tokens in the emitted style block, and no browser console errors.

## Issue found and fixed

The pre-update live build used a native `window.confirm` for converted-theme
installation. This caused the install action to be interrupted in browser
automation and made the one-step ZIP workflow depend on a host dialog. The
new build removes that native-confirm dependency while preserving explicit
backend fields `approved=true` and `confirmation=INSTALL`. The backend still
performs its own signature, integrity, permission and lifecycle checks.

The older Pinoox Manager UI also exposed non-responsive install controls in
this target session. The NanoPino Extension Center API was used for the
authenticated app update after the UI boundary was confirmed; this does not
change the target security or lifecycle checks.

## Verification

- Source verification: PASS; 243 Node tests, 75 PHP tests, 871/871 PHP lint
- Build: PASS; 62 production assets and all size budgets passed
- Source/dist and runtime parity: PASS
- Target boundary probe: PASS (manager/public HTTP 200; private routes remain
  HTTP 401 without authentication)
- Appearance, Full Site Editor and public-site browser console errors: none
