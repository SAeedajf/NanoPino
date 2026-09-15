# Architecture Overview

NanoPino is the product packaged through the preserved Pinoox app key
`com_pinoox_cms`. The independent platform identity is NanoShell
(`nanoshell-platform-v1`); the package key is transport compatibility, not the
platform name.

Core architecture:
- **Kernel/registries** own capabilities, settings, content types, taxonomies, blocks, themes, extensions and admin definitions.
- **Domain services** implement content, media, settings, builder, themes, users, search, recovery and infrastructure behavior.
- **Runtime services** in `CmsRuntimeServices` compose domain services with Pinoox adapters.
- **HTTP API** is registered from `CmsRuntimeApiManifest` under `/api/v1/cms`.
- **Admin** is Vue + Luma, mobile-first and RTL-aware.
- **Persistence** uses Pinoox database/storage primitives plus bounded file repositories where explicitly designed.
- **Extensions** use PINX, review/preflight, recovery points and fail-closed quarantine.
- **Platform identity** is registered by the NanoShell contract. Source-format
  readers are intake adapters only and cannot redefine or execute the NanoShell
  runtime.

The project rule is source-first: repair the owning layer rather than placing a replacement layer over a broken implementation.
