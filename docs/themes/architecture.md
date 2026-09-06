# Theme Architecture

NanoPino themes use the native Pinoox theme stack. Theme discovery, inheritance and activation remain Pinoox-backed.

NanoPino adds CMS theme profile parsing, compatibility checks, template/part/pattern discovery, design-token/global-style overrides and Builder integration.

Patterns are discovered from the active theme stack and exposed to Builder without exposing source filesystem paths. Site overrides are stored separately; normal customization does not mutate theme source files.
