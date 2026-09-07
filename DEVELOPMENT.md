# NanoPino Development Guide

## 1. Source tree

The installable Pinoox application source lives under:

```text
payload/
```

The top-level `manifest.json` is the PINX package manifest.

## 2. Backend

Main backend areas are under:

```text
payload/Cms/
payload/Controller/
payload/Model/
payload/routes/
payload/database/migrations/
payload/resources/api/
```

Use Pinoox/Pincore contracts, routing, lifecycle, events, RBAC, database and extension facilities before adding new infrastructure.

Do not modify Pincore or vendor code for application business logic.

## 3. Admin frontend

Admin source:

```text
payload/theme/cms-admin/
```

Important directories:

- `src/` — Vue source
- `runtime/` — runtime integration modules
- `tests/` — frontend/source contract tests
- `dist/` — production build output
- `package.json` — frontend scripts/dependencies
- `package-lock.json` — deterministic dependency lock

Typical workflow:

```bash
cd payload/theme/cms-admin
npm ci
npm test
npm run build
```

## 4. PHP validation

```bash
find payload -type f -name '*.php' -print0 |
  xargs -0 -n1 php -l
```

## 5. Pinoox local development

For a local Pinoox installation, stage the `payload/` contents as:

```text
apps/com_pinoox_cms/
```

The package metadata remains:

- package: `com_pinoox_cms`
- version: `0.23.29`
- version code: `2329`
- minimum Pincore: `3.14.0`
- native minimum kernel code (minpin): `232`

## 6. Building PINX locally

Use NanoPino's release wrapper, which invokes the native Pinoox builder and then audits the generated PINX. Do not handcraft a ZIP, rename a generic ZIP, or bypass the artifact verifier.

```bash
tools/release/verify-source.sh
tools/release/build-pinx.sh /path/to/pinoox /path/to/NanoPino.pinx
```

The wrapper runs native `pinx:build`, `pinx:info`, the R15 installability artifact audit and SHA-256 generation. For production distribution, signing and the remaining Stable release gates must also be completed.

## 7. Database

NanoPino reuses the native Pinoox platform database connection and sets a valid MySQL/MariaDB engine override where required.

R15 contains 19 package migrations: one read-only environment preflight followed by the existing schema/repair/index migrations. The runtime schema contract remains 16 required CMS tables.

Repository lifecycle CI verifies a native 0.23.28 → 0.23.29 update without force plus a separate clean 0.23.29 install/uninstall on the declared Pincore matrix.

## 8. Contribution boundaries

Changes should be implemented through:

- Contracts
- Registries
- Events / Hooks
- Modules
- Plugins
- Themes
- Blocks
- Drivers

A feature that requires editing Pincore/vendor should be treated as a missing extension contract and redesigned accordingly.


## 9. R15 executable PHP tests

Run the dependency-light PHP domain suite with:

```bash
php tests/php/run.php
```

Repository CI runs this suite on PHP 8.2, 8.3, 8.4 and 8.5. See `docs/deployment/installability.md` before changing package requirements, minpin, database assumptions or build exclusions.
