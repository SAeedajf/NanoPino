# NanoPino Source-First Update Workflow

NanoPino uses the extracted Git repository as the development source of truth.

## Source of truth

- GitHub stores extracted source only.
- `payload/` is the installable Pinoox application source.
- `.pinx` files are build artifacts and must not be committed.
- `node_modules/` is never part of the repository or PINX payload.

## Versioning

Use:

```bash
php tools/release/bump-version.php <version-name> <version-code>
```

The command updates:

- `payload/app.php`
- root `manifest.json`
- `payload/resources/release/release-metadata-v1.json`
- `app_sha256` release evidence

Version code must increase monotonically.

## Verification

Run:

```bash
tools/release/verify-source.sh
```

The verifier fails closed when:

- package artifacts exist in the source tree;
- release metadata does not match `app.php`;
- PHP lint fails;
- frontend tests fail;
- Admin source/dist parity fails.

## Building PINX

`pinx:build` does not accept an arbitrary source path, so NanoPino uses a dedicated Pinoox build root.

```bash
PHP_BIN=php8.4 tools/release/build-pinx.sh /path/to/pinoox-build-root /path/to/output.pinx
```

The build root must not already contain `apps/com_pinoox_cms`.
The build script stages `payload/` there, invokes the native Pinoox `pinx:build`, validates with `pinx:info`, prints SHA-256, and removes staging on exit.

## Update validation

Before publishing an RC update:

1. Install the previous RC in a real Pinoox runtime.
2. Capture schema/table counts and selected preservation probes.
3. Build the new PINX from the Git source.
4. Run `pinx:install` with the newer package so Pinoox enters update mode.
5. Confirm version transition.
6. Confirm all migrations complete or remain applied.
7. Confirm existing CMS tables/data remain present.
8. Smoke-test Manager/App View/API endpoints.

## Stable release

Stable 1.0 additionally requires signing, authenticated browser E2E, production security/performance evidence, and zero release blockers.
