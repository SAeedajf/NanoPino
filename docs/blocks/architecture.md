# Block Architecture

Blocks are registry-owned definitions with schema/version, attributes, child rules, renderer metadata and optional migrations.

The canonical editable form is a block document, not rendered HTML. The server renderer remains the output boundary.

Reusable blocks are stored as Global Blocks. Builder inserts a `core/global-reference` node containing a `globalId`; preview expansion resolves the referenced document, checks site ownership, prevents cycles and remaps IDs.

Extension block definitions remain owned by their extension and must not be copied into core.
