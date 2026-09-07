# Block Architecture

Blocks are registry-owned definitions with schema/version, attributes, child rules, renderer metadata and optional migrations.

The canonical editable form is a block document, not rendered HTML. The server renderer remains the output boundary.

Reusable blocks are stored as Global Blocks. Builder inserts a `core/global-reference` node containing a `globalId`; preview expansion resolves the referenced document, checks site ownership, prevents cycles and remaps IDs.

Extension block definitions remain owned by their extension and must not be copied into core.


## Core catalog policy

NanoPino Core intentionally ships a small foundational block set. Specialized capabilities such as image, gallery, video, navigation and forms are expected to be supplied by registered Block Packs when they are not present.

The Admin Block Catalog detects capabilities across third-party namespaces rather than requiring `core/*` identifiers. This allows a package such as `vendor/gallery` to satisfy the gallery capability without a Core edit.

The catalog links installation/removal to Extension Center and usage/configuration to Builder. Block ownership, renderer ownership and migration ownership remain with the registering extension.
