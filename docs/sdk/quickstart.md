# SDK Quickstart

## Admin-first starter flow

1. Open **Developer → SDK** and choose the Extension type.
2. Enter the stable `com_...` package ID, human name, publisher and version. Theme starters also require target app and theme name.
3. Generate the starter. Download the ZIP when `ZipArchive` is available; otherwise download the generated source files individually.
4. Extend only through SDK/Registry/Pinoox Native contracts. Do not modify NanoPino Core, Pincore or vendor.
5. Run the generated test under `tests/` and add domain/API/UI tests for your real feature.
6. Build the final package with official Pinoox PINX tooling.
7. Upload the PINX in Extension Center and complete Inspect → Review → dependency/permission checks → controlled install/update.
8. Validate activation, Admin/API surfaces, recovery and uninstall.

Starter generation is intentionally separate from installation and does not require SSH. It is guarded by `system.developer.generate`; Core Module generation is not exposed from Admin.

The authoritative interfaces are `payload/resources/sdk/sdk-contract-v1.json`, the extension manifest schema, and `payload/resources/api/developer-v1.json`. Generated examples are development scaffolds, not finished operational features.
