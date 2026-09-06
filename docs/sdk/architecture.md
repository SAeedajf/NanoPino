# SDK Architecture

The NanoPino SDK extends Pinoox registration primitives rather than replacing them. Extension-owned definitions may register capabilities, settings, fields, taxonomies, content types, themes, templates, blocks, drivers and admin surfaces.

Native Pinoox registration remains the boundary for routes, actions, listeners, schedules and host-app integration.

Ownership is enforced: an extension must not silently replace definitions owned by another extension or the core. Public SDK behavior is described by `payload/resources/sdk/sdk-contract-v1.json`.
