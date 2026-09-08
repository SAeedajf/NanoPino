# SDK Quickstart

1. Build a normal Pinoox/PINX extension package.
2. Give the package a stable `com_...` identifier.
3. Select one NanoPino semantic Extension type from the SDK contract.
4. Declare the NanoPino `cms` profile required by that type.
5. Put vendor-specific/custom Manifest data under `cms.metadata`; do not add undocumented top-level `cms` keys.
6. Register definitions through the NanoPino/Pinoox registries; do not modify core files.
7. Package and inspect the PINX file through Extension Center.
8. Review compatibility, dependencies, permissions and static-risk findings.
9. Install only after review passes.
10. Validate activation, API/admin surfaces and uninstall/recovery behavior.

`block` and `block-package` packages must declare `cms.blocks`. Themes should use the nested `cms.theme` profile; legacy flat theme-profile keys exist only for 0.x migration compatibility.

Use the machine SDK contract and extension manifest schema as the authoritative interface. The SDK generator, PHP validator and machine schema are regression-tested against the same Extension type set. Example packages are not considered supported unless their source exists and is covered by tests.
