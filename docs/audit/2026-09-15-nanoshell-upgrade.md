# NanoShell Upgrade Audit

Date: 2026-09-15

Status: local source upgrade implemented; GitHub, artifact signing and target
deployment intentionally deferred.

## Scope

This upgrade keeps the public `nanoshell-platform-v1` contract stable and adds
machine-readable capability discovery plus explicit host compatibility metadata.
It does not introduce a second platform registry or change the package
transport key.

## Implemented

- Canonical PHP profile now exposes seven bounded capabilities and a
  `compatibility` block with contract, minimum version and host binding.
- `NanoShellPlatform::supports()` provides server-side capability discovery.
- `assertCompatible()` gives adapters and future extensions a fail-closed
  compatibility gate; strict `assertProfile()` validation remains available for
  exact metadata parity.
- App metadata, both manifests, Admin bootstrap, Vue services, theme config,
  extension host and direct runtime use the same upgraded profile.
- `cmsPlatformSupports()` and `platformSupports()` provide client-side discovery
  without duplicating platform identity constants in feature modules.
- Cached normalization remains in place; malformed or incomplete runtime
  profiles resolve to the safe v1 fallback.

## Verification gates

Final local verification after the frontend rebuild:

- PHP lint: `866/866` files pass.
- PHP tests: `66/66` pass.
- Frontend tests: `238/238` pass.
- Production build: PASS; JavaScript `1,876,491` bytes and CSS `221,991`
  bytes, within configured budgets.
- Source/dist parity: PASS (`8cbf9f5872d8616815d2d79c81b2aece74683745a28eb2d821509e42b8272939`,
  51 source files).
- Runtime parity: PASS (`f3a7eaae4e701f1003c6ee514be98b86091fe7ab61ac1dd8cb8098377a467d35`,
  17 runtime files).
- Production assets: `62` verified; root-domain asset references: `0`.
- `git diff --check`: PASS.

A passing local source gate does not claim a new PINX artifact, target
installation, live deployment or GitHub publication.

## Follow-up boundaries

The next release operation must rebuild the frontend and PINX from this source
freeze, regenerate integrity metadata, sign the artifact with the approved
trust chain, then run target canary and live health checks independently.
