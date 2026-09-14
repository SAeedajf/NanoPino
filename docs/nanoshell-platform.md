# NanoShell Platform

## Identity

NanoShell is the independent platform contract behind the NanoPino product.
Its canonical machine identity is `nanoshell`, contract version
`nanoshell-platform-v1`, and its public runtime is native-only. The legacy
package identifier remains only as a transport/install compatibility value; it
does not define the platform identity.

The registration is present in all source metadata boundaries:

- `payload/app.php` under `cms.platform`;
- `manifest.json` and `payload/manifest.json` under `cms.platform`;
- `cms.provides` as `nanoshell.platform`;
- `CmsRuntimeServices::nanoShellPlatform()`;
- `NanoShellPlatform::profile()` as the canonical code contract.

The Admin bootstrap emits this profile once under `cmsAdmin.platform`.
Canonical Vue consumers, extension hosts and direct runtime modules reuse that
single value; fallback normalization is cached and rejects malformed identities.

## Platform boundary

NanoShell owns the native block-document, CMS API, authorization, storage,
theme, Builder, recovery and extension contracts. Public runtime behavior is
provided by NanoShell services and native Pinoox bindings. Source-format
readers are adapters at the intake edge; they cannot change NanoShell identity,
execute source code or become a public runtime dependency.

The current profile declares only bounded static intake adapters. It does not
declare arbitrary template execution, plugin execution, remote asset loading,
or source-runtime compatibility. Those capabilities require explicit future
adapters, review, permission declarations and rollback evidence.

## Compatibility and migration rule

NanoPino is the product brand and `com_pinoox_cms` is the preserved package
transport key. New public metadata, runtime services and platform checks must
use NanoShell. Historical source-adapter class names and migration documents
may remain temporarily for backward-compatible conversion tests, but they are
not allowed to appear as the platform identity or as a runtime requirement.

## Verification

Phase 9 verifies that the same NanoShell profile is projected into the app and
both package manifests, that the package advertises `nanoshell.platform`, and
that a profile containing a legacy source identity is rejected. This is source
metadata verification only; the artifact, target installation and live platform
remain separate release gates.
