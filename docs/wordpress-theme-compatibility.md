# WordPress Theme Compatibility

## Scope

NanoPino treats WordPress Theme support as an import and conversion boundary.
It does not execute an untrusted WordPress `functions.php`, template PHP, or
plugin code inside the public runtime. A WordPress Theme must first be scanned,
classified, converted to a native NanoPino Theme contract, validated, previewed,
and only then considered for activation.

## Compatibility tiers

| Tier | Input | Planned result |
| --- | --- | --- |
| 1 | Standard Block Theme | Convert `theme.json`, block templates, parts, patterns and style variations. |
| 2 | Simple Classic Theme | Convert safe presentation templates and map supported data sources. |
| 3 | Hybrid Theme | Use the Block path where possible and report Classic-only gaps. |
| 4 | Plugin-dependent Theme | Require explicit adapters for WooCommerce, Elementor, ACF, shortcodes and custom blocks. |
| 5 | Arbitrary WordPress runtime | Not a default production target; requires an isolated WordPress worker. |

## Phase 1 foundation

`WordPressThemeScanner` is a read-only intake boundary. It:

- classifies Block, Classic, Hybrid and Unknown layouts;
- reads the standard `style.css` metadata headers;
- validates `theme.json` without executing theme code;
- detects templates, parts, patterns, style variations and Block Markup;
- detects hooks, template tags, shortcodes and dynamic blocks;
- reports known plugin/extension dependencies;
- bounds file count, file size, total size and filesystem traversal;
- skips symlinks and refuses paths outside the inspected root;
- returns estimated conversion scores and next actions.

In the report, `safe_to_import` means that the bounded static intake/conversion
pipeline may accept the directory after blocker review. It does not mean that
the theme is activation-ready or that its PHP/plugin behavior is supported.

The scores are triage estimates, not a claim of rendered fidelity. They must be
replaced or refined by fixture-based conversion and browser visual tests in
later phases.

## Phase 2 intake boundary

`WordPressThemeIntakeService` accepts either a theme directory or a ZIP archive
for read-only inspection. It builds a deterministic SHA-256 file manifest,
rejects traversal, absolute paths, case collisions, symlinks and bounded
resource violations, and extracts only non-executable metadata. A provenance
envelope can be signed and verified with Ed25519 against an explicit trusted
publisher-key map. A missing signature is an inspection warning; an untrusted
or invalid signature is a blocker. No extraction, installation or activation is
performed by this service.

## Phase 3 Block Markup boundary

`WordPressBlockMarkupParser` parses nested serialized block comments without
executing PHP, shortcodes or dynamic blocks. WordPress core shorthand such as
`wp:paragraph` is normalized to `core/paragraph`; namespaced blocks remain
namespaced. Paragraph, heading and button blocks map directly to canonical
NanoPino nodes, including bounded text, safe URLs, nested JSON attributes and
selected style values.
Other blocks become warning-bearing structural `core/section` wrappers so their
children remain available for later specialized adapters. Malformed nesting,
invalid attributes and parser budget violations fail closed.

## Planned implementation phases

1. Scanner and compatibility report.
2. Import intake, archive safety, license and signed provenance. The local
   trust boundary accepts unsigned sources for inspection only; conversion and
   activation must apply the configured publisher trust policy.
3. Block Markup parser and canonical NanoPino block mapping. Supported core
   paragraph, heading and button blocks map directly; unsupported blocks keep
   their children inside a warning-bearing structural section wrapper.
4. `theme.json` compiler, style variations and design-token bridge.
5. Template/part/pattern hierarchy conversion and Builder integration.
6. Query/data binding for content, taxonomy, media, navigation and pagination.
7. Asset pipeline for CSS, JavaScript, fonts, RTL and dependency order.
8. Isolated Classic Theme conversion worker and unsupported-feature report.
9. Plugin adapters, preview, cache invalidation and activation rollback.
10. Fixture corpus, browser/WCAG, security, performance and Canary gates.

Phases 1–3 deliberately stop before theme installation, activation and public
runtime execution. GitHub and deployment changes remain deferred until the
complete compatibility implementation has passed its local and target gates.
