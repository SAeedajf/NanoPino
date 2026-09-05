# RC11 Update Validation

## Target

- Package: `com_pinoox_cms`
- Product: NanoPino
- Previous: `0.23.26 #2326` (RC10)
- Update: `0.23.27 #2327` (RC11)
- Runtime: local Pinoox at `127.0.0.1:8000`

## Build

RC11 was built from the extracted Git source tree using:

```bash
PHP_BIN=php8.4 tools/release/build-pinx.sh /home/saeed/Projects/pinoox-build-nanopino /path/to/output.pinx
```

Native Pinoox build result:

- `pinx:build`: PASS
- `pinx:info`: PASS
- Files: 896 payload files / 897 archive entries
- Signed: no
- SHA-256: `195661d7cfee246d18ffcd9adcb65c4fb227910c071913650951b0c9f9fd0811`

## Source verification

- Release metadata: PASS
- PHP lint: 782/782
- Frontend tests: 66/66
- Production assets: 54 verified
- Source/dist parity: PASS
- Root-domain `/assets/` references: 0

## Native update

Pinoox detected the package as an update:

```text
Mode: update
App update: com_pinoox_cms #2326 → #2327
```

Installer stages:

- validate: PASS
- minpin: PASS
- detect update: PASS
- extract: PASS
- pinker rebuild: PASS
- migrations: PASS
- cache rebuild: PASS
- complete: PASS

No custom `lifecycle.php` update handler was required for RC11.

## Database preservation

Before update:

- CMS tables: 16
- Applied migrations: 18

After update:

- CMS tables: 16
- Applied migrations: 18
- Table set preserved: PASS
- Row-count snapshot preserved: PASS

## Runtime smoke

- `/manager/app/com_pinoox_cms`: HTTP 200
- `/manager/`: canonical/auth redirect observed
- No new SQLSTATE/Fatal/uncaught/5xx entry found in the local serve log

Without an authenticated Manager session, the App View intentionally renders the login-required state. Authenticated browser E2E remains a separate release blocker.

## Result

**RC10 → RC11 native update contract: PASS.**

RC11 remains unsigned and is not Stable 1.0.
