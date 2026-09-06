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
- version: `0.23.27`
- version code: `2327`
- minimum kernel: `205`

## 6. Building PINX locally

Use the native Pinoox CLI. Do not handcraft a ZIP and rename it.

Example:

```bash
php pinoox pinx:build com_pinoox_cms --output=/path/to/NanoPino.pinx --no-sign --yes
```

For production distribution, signing and release gates must be completed before Stable 1.0.

## 7. Database

NanoPino reuses the native Pinoox platform database connection and sets a valid MySQL/MariaDB engine override where required.

RC10 fresh-install validation completed all 18 migrations and created the required CMS tables.

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
