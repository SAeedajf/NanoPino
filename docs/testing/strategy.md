# Testing Strategy

Repository CI is a release gate, not a substitute for target-runtime E2E.

Current CI sequence:
1. locked `npm ci`;
2. production admin build;
3. `tools/release/verify-source.sh`;
4. PHP lint;
5. Node contract/regression tests;
6. source/runtime/dist parity checks;
7. verified admin artifact upload.

Test categories include API completeness, mount safety, Builder state integrity, content/media/users centers, theme compatibility, security hardening, mobile/RTL and release tooling.

Open gap: NanoPino still needs a dedicated PHP unit/integration suite against a controlled Pinoox/database runtime.
