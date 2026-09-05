# NanoPino

NanoPino is a next-generation CMS and Visual Site Builder for Pinoox/Pincore.

This repository contains the **open, extracted and development-ready source tree**.  
It intentionally does **not** store packaged `.pinx` artifacts.

## Current source baseline

- Product: NanoPino
- Package ID: `com_pinoox_cms`
- Version: `0.23.27`
- Version code: `2327`
- Release baseline: `RC11`
- Minimum Pinoox kernel: `205`

## Repository layout

```text
NanoPino/
├── manifest.json
├── payload/
│   ├── app.php
│   ├── boot.php
│   ├── Cms/
│   │   ├── API/
│   │   ├── Content/
│   │   ├── Extensions/
│   │   ├── Media/
│   │   ├── Performance/
│   │   ├── Security/
│   │   ├── Support/
│   │   └── ...
│   ├── Controller/
│   ├── Model/
│   ├── database/
│   │   └── migrations/
│   ├── lang/
│   │   ├── en/
│   │   └── fa/
│   ├── resources/
│   │   ├── api/
│   │   └── release/
│   ├── routes/
│   └── theme/
│       └── cms-admin/
│           ├── src/
│           ├── runtime/
│           ├── tests/
│           ├── dist/
│           ├── package.json
│           ├── package-lock.json
│           └── vite.config.js
├── DEVELOPMENT.md
└── .gitignore
```

## Development model

NanoPino follows the Pinoox-native extension architecture:

```text
Pinoox
  → Pincore
    → NanoPino CMS Kernel
      → Core Domains
      → Registries / Contracts / Hooks
      → Modules / Plugins / Themes / Blocks
      → Visual Builder
      → Admin UI
```

Pincore and vendor code must not be modified for NanoPino business logic.

## Admin stack

- Vue 3
- @pinooxhq/luma
- PrimeVue
- Pinia
- Vue Router
- Vite
- RTL/LTR-aware i18n

## RC11 validation baseline

- Native Pinoox update RC10 → RC11: PASS
- Native Pinoox fresh-install baseline: PASS
- Migrations: 18/18 PASS
- Required CMS tables: 16/16
- Update DB table-set preservation: PASS
- Update row-count preservation: PASS
- PHP lint: 782/782 PASS
- Frontend tests: 66/66 PASS
- i18n contract: 732/732
- Hard-coded Persian UI copy: 0
- Forced RTL / hard-coded fa-IR: 0
- Source/dist parity: PASS

RC11 is still a release candidate and is not Stable 1.0.
