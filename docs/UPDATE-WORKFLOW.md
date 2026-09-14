# NanoPino Source-First Update Workflow

NanoPino uses the extracted Git repository as the development source of truth.

> **Checkout status (2026-09-10):** the historical helper scripts
> `tools/release/build-pinx.sh`, `tools/release/verify-source.sh` and
> `tools/release/bump-version.php` are **Not found in current codebase**. The
> checkout does provide the native Pinoox commands `pinx:build`, `pinx:info`
> and `release`; use those commands for the current source tree.

## Source of truth

- GitHub stores extracted source only.
- `payload/` is the installable Pinoox application source.
- `.pinx` files are build artifacts and must not be committed.
- `node_modules/` is never part of the repository or PINX payload.

## Versioning

Native current-checkout command:

```bash
php pinoox release com_pinoox_cms --bump=<patch|minor|major|version-name> --yes
```

The command updates:

- `payload/app.php`
- root `manifest.json`
- `payload/resources/release/release-metadata-v1.json`
- `app_sha256` release evidence

Version code must increase monotonically.

## Verification

Native current-checkout verification command:

```bash
php pinoox pinx:info /path/to/NanoPino.pinx
```

The verifier fails closed when:

- package artifacts exist in the source tree;
- release metadata does not match `app.php`;
- PHP lint fails;
- frontend tests fail;
- Admin source/dist parity fails.

## Building PINX

`pinx:build` does not accept an arbitrary source path, so NanoPino uses a dedicated Pinoox build root.

The dedicated `tools/release/build-pinx.sh` helper referenced by the historical
process is not present in the current checkout. Do not treat a manually renamed
ZIP as a PINX; use the native Pinoox builder and record both commands:

```bash
php pinoox pinx:build com_pinoox_cms --no-sign --yes --output=/path/to/NanoPino.pinx
php tools/release/ensure-pinx-cms-profile.php /path/to/NanoPino.pinx
php pinoox pinx:info /path/to/NanoPino.pinx
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
