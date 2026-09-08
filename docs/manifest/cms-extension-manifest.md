# CMS Extension Manifest

NanoPino extensions are transported through Pinoox PINX. The native PINX transport remains authoritative for package identity, payload integrity, signatures and lifecycle execution; the NanoPino `cms` profile adds semantic Extension metadata on top of that transport.

The machine schema is authoritative:

`payload/resources/schemas/cms-extension-manifest-v1.schema.json`

## Semantic Extension types

Schema v1 supports the same semantic types as `ExtensionType` and the SDK contract:

- `core-module`
- `module`
- `plugin`
- `integration`
- `theme`
- `admin-extension`
- `block`
- `block-package`
- `driver`
- `language-pack`

PINX transport type is still only `app` or `theme`: `theme` semantic extensions use PINX `type=theme`; every other semantic type uses PINX `type=app`.

Both `block` and `block-package` require a `cms.blocks` profile.

## Strict third-party CMS profile

Third-party extensions must use the documented top-level `cms` keys. Extension-specific or vendor-specific values belong under `cms.metadata`; unknown third-party top-level keys are rejected instead of being silently ignored. This keeps spelling mistakes such as `permission` instead of `permissions` fail-closed.

`core-module` remains forward-compatible with NanoPino-owned internal release/runtime metadata because the CMS package itself publishes additional internal evidence under `cms`.

Themes should use the canonical nested `cms.theme` profile. The earlier flat theme-profile keys remain accepted only as 0.x migration compatibility and should not be used for new packages.

The extension lifecycle validates identity, package integrity, compatibility, dependencies, requested permissions and package safety before mutation. Installed extension state is discovered from the Pinoox app registry; NanoPino must not invent a parallel package loader.

Breaking manifest changes require a migration note, tests and machine-schema update.
