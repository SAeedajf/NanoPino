# NanoPino

NanoPino is a next-generation CMS and Visual Site Builder for Pinoox/Pincore.

This repository contains the **open, extracted and development-ready source tree**.  
It intentionally does **not** store packaged `.pinx` artifacts.

## Current source baseline

- Product: NanoPino
- Package ID: `com_pinoox_cms`
- Version: `0.23.29`
- Version code: `2329`
- Release hardening baseline: `R15`
- Minimum Pincore: `3.10.0`
- Native minimum kernel code: `216`

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



## Current installability gates

NanoPino 0.23.29 adds release-grade installability checks around the native Pinoox PINX lifecycle:

- PHP runtime suite on 8.2–8.5;
- native PINX structure/version/minpin/artifact audit;
- read-only environment preflight before CMS schema creation;
- Pincore 3.10.0 and 3.14.4 lifecycle matrix;
- native 0.23.28 → 0.23.29 update without force;
- separate clean 0.23.29 fresh install/uninstall;
- MySQL table-count and uninstall-cleanup invariants;
- development-only Admin source/build files excluded from the distributable PINX.

See [Installation Readiness and Recovery](docs/deployment/installability.md) for shared-hosting requirements and failure recovery.

## Historical RC11 validation baseline

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

## Current audit and corrective branch

See [the 2026-09-06 audit](docs/audit/2026-09-06-NANOPINO-AUDIT.md) for reproduced state-integrity defects, corrections, evidence limits and all 25 phase gates. The historical checks above do not certify subsequent commits. Stable 1.0 remains blocked by native lifecycle/database recovery, authorization isolation, documentation and authenticated browser verification.
