# SDK Quickstart

1. Build a normal Pinoox/PINX extension package.
2. Give the package a stable `com_...` identifier.
3. Declare the NanoPino semantic profile required by the extension.
4. Register definitions through the NanoPino/Pinoox registries; do not modify core files.
5. Package and inspect the PINX file through Extension Center.
6. Review compatibility, dependencies, permissions and static-risk findings.
7. Install only after review passes.
8. Validate activation, API/admin surfaces and uninstall/recovery behavior.

Use the machine SDK contract and extension manifest schema as the authoritative interface. Example packages are not considered supported unless their source exists and is covered by tests.
