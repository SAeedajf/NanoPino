# CMS Extension Manifest

NanoPino extensions are transported through Pinoox PINX and may expose NanoPino semantic metadata. The machine schema is authoritative:

`payload/resources/schemas/cms-extension-manifest-v1.schema.json`

The extension lifecycle validates identity, package integrity, compatibility, dependencies, requested permissions and package safety before mutation. Installed extension state is discovered from the Pinoox app registry; NanoPino must not invent a parallel package loader.

Breaking manifest changes require a migration note and machine-schema update.
