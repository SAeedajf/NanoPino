# Theme Architecture

NanoPino themes use the NanoShell theme contract on top of the native host theme stack. Theme discovery, inheritance and activation remain host-backed, while NanoShell owns the CMS theme profile, Builder-facing capabilities and public rendering contract.

The package key `com_pinoox_cms` is retained only as an installation and transport compatibility identifier. It is not the platform identity. Source-format import readers are bounded intake adapters and cannot rename or redefine NanoShell.

NanoPino adds CMS theme profile parsing, compatibility checks, template/part/pattern discovery, design-token/global-style overrides and Builder integration.

Patterns are discovered from the active theme stack and exposed to Builder without exposing source filesystem paths. Site overrides are stored separately; normal customization does not mutate theme source files.


## Active theme scope

Pinoox maintains a native theme stack per application package. NanoPino does not collapse those stacks into one global theme.

The admin Theme Center therefore distinguishes:
- the active theme for the NanoPino site package;
- active themes belonging to other Pinoox applications.

Only the NanoPino site-package theme exposes NanoPino Builder/Full Site Editor actions. Other application themes remain discoverable for diagnostics and package management without being presented as the current site theme.
