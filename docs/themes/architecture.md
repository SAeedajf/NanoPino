# Theme Architecture

NanoPino themes use the native Pinoox theme stack. Theme discovery, inheritance and activation remain Pinoox-backed.

NanoPino adds CMS theme profile parsing, compatibility checks, template/part/pattern discovery, design-token/global-style overrides and Builder integration.

Patterns are discovered from the active theme stack and exposed to Builder without exposing source filesystem paths. Site overrides are stored separately; normal customization does not mutate theme source files.


## Active theme scope

Pinoox maintains a native theme stack per application package. NanoPino does not collapse those stacks into one global theme.

The admin Theme Center therefore distinguishes:
- the active theme for the NanoPino site package;
- active themes belonging to other Pinoox applications.

Only the NanoPino site-package theme exposes NanoPino Builder/Full Site Editor actions. Other application themes remain discoverable for diagnostics and package management without being presented as the current site theme.
