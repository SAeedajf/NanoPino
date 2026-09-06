# Architecture Overview

NanoPino is packaged as the Pinoox app `com_pinoox_cms`. The brand is NanoPino while the legacy package identifier is intentionally preserved for compatibility.

Core architecture:
- **Kernel/registries** own capabilities, settings, content types, taxonomies, blocks, themes, extensions and admin definitions.
- **Domain services** implement content, media, settings, builder, themes, users, search, recovery and infrastructure behavior.
- **Runtime services** in `CmsRuntimeServices` compose domain services with Pinoox adapters.
- **HTTP API** is registered from `CmsRuntimeApiManifest` under `/api/v1/cms`.
- **Admin** is Vue + Luma, mobile-first and RTL-aware.
- **Persistence** uses Pinoox database/storage primitives plus bounded file repositories where explicitly designed.
- **Extensions** use PINX, review/preflight, recovery points and fail-closed quarantine.

The project rule is source-first: repair the owning layer rather than placing a replacement layer over a broken implementation.
