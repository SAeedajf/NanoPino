# Phase 9 — NanoShell Platform Identity Audit

Date: 2026-09-15

Status: source implementation complete; artifact, target and remote publication intentionally deferred.

## Decision

NanoPino now registers `NanoShell` as its independent platform identity:

| Field | Value |
|---|---|
| Platform id | `nanoshell` |
| Platform name | `NanoShell` |
| Contract | `nanoshell-platform-v1` |
| Contract version | `1` |
| Runtime mode | `native-only` |
| Document contract | `block-document-v1` |
| API contract | `cms-api-v1` |
| Product/package brand | `NanoPino` / `com_pinoox_cms` |

`NanoShell` is the platform identity. `NanoPino` remains the product/package brand. The package key is preserved for installation and transport compatibility; it is not used as the platform name.

## Registration points

- `payload/Cms/Platform/NanoShellPlatform.php` is the canonical profile and validator.
- `payload/Cms/Runtime/CmsRuntimeServices.php` exposes the profile to runtime consumers.
- `payload/Controller/AdminController.php` projects the profile into the admin bootstrap payload.
- `payload/app.php`, `manifest.json` and `payload/manifest.json` project the same profile into application and package metadata.
- `nanoshell.platform` is declared in the CMS capability list.
- `docs/nanoshell-platform.md` defines the boundary and migration rules.
- `payload/theme/cms-admin/main.twig` publishes the identity before JavaScript boot.
- The canonical Admin shell consumes the profile for document identity, theme configuration and extension host context.
- The direct runtime fallback consumes the same contract and System Center displays its id, contract and version.

The Phase 9 test requires the runtime profile and all metadata projections to be exactly equal. It also verifies that a legacy source identity cannot be accepted as a NanoShell profile.

## Boundary policy

The existing source-format compatibility readers remain adapters only. Their historical class and documentation names are not platform identity, runtime ownership or execution permission. They are retained for compatibility tests and controlled static intake until a separately approved rename/migration removes those names.

NanoShell currently owns the native block document, CMS API, authentication/session, storage, theme, Builder, recovery and extension contracts. No external source-format runtime is enabled by this registration.

## Evidence

- PHP lint: `866/866` files pass.
- PHP tests: `65/65` pass.
- Frontend tests: `238/238` pass.
- Release verification: PASS.
- Source/dist parity: PASS (`37c1b26fc41be5219d83bb4cfaeeaa563f862c324150ff0a350715d937c3d4e5`, 51 source files).
- Runtime parity: PASS (`2d287c1b6447c259feec955e71977a140cd8b7145a98a354f5d0a2b16153a880`, 17 runtime files).
- Production asset verification: `62` assets pass.
- Performance budgets: PASS; total emitted JavaScript/CSS remained within configured budgets.
- The release metadata `app_sha256` was refreshed after the bootstrap metadata change.

## Remaining gates

This phase does not claim a standalone artifact or live installation. The following remain separate release gates:

1. rebuild and sign the artifact after the final source freeze;
2. install and verify NanoShell on the real target;
3. migrate or rename legacy adapter identifiers only if a zero-legacy-name policy is required;
4. add a host-independent package/boot path if “fully independent” means running outside the Pinoox host;
5. publish to GitHub only after approval of the completed change set.
