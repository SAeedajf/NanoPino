# Installation Readiness and Recovery

NanoPino installation and update must use the native Pinoox PINX lifecycle. Do not extract a PINX manually into `apps/` and do not rename a generic ZIP to `.pinx`.

## Current installability contract

| Requirement | NanoPino 0.23.29 |
|---|---|
| Package | `com_pinoox_cms` |
| Version | `0.23.29` / code `2329` |
| PHP | `>= 8.2` |
| Pincore | `>= 3.14.0` |
| Native minpin | `232` |
| Database | MySQL/MariaDB package connection |
| Tested CI database | MySQL 8.4 |
| Required CMS tables | 16 |
| Required PHP extensions | ctype, fileinfo, filter, hash, json, mbstring, openssl, pdo, tokenizer, zip |
| MySQL/MariaDB extension | pdo_mysql |
| Hard memory floor | 32 MiB |
| Recommended memory | 128 MiB or more |
| Recommended upload/post budget | 8 MiB or more |
| Recoverable free-space floor | 32 MiB |
| Storage engine | InnoDB |
| Recommended database charset | utf8mb4 |

GD or Imagick is recommended for image processing. OPcache is recommended in production. Their absence is reported as a warning rather than an installation blocker.

The repository CI executes the PINX lifecycle against Pincore 3.14.0 and 3.14.4. A target shared-hosting environment must still be validated independently because hosting policy, PHP builds, permissions and database privileges can differ.

## Native installation sequence

1. Pinoox validates the PINX structure, signature policy, package name, dependencies and `minpin`.
2. Pinoox extracts the package.
3. NanoPino's first package migration runs the read-only installability preflight.
4. Only after the preflight passes do the remaining NanoPino migrations create or repair the 16 CMS tables.
5. Pinoox runs package patches/lifecycle hooks and rebuilds runtime caches.
6. Post-install health checks can validate the resulting runtime.

The native `minpin=232` check intentionally rejects Pincore older than 3.14.0 before package extraction. The first NanoPino migration then catches environment problems that cannot be expressed through PINX metadata before CMS schema mutation.

## What the self-preflight blocks

Installation is stopped when any of the following is true:

- PHP is older than 8.2.
- A required PHP extension is missing.
- PHP memory is below 32 MiB.
- the extracted NanoPino root or its application parent is not writable.
- the PHP temporary directory is not writable.
- less than 32 MiB is available on the application filesystem.
- a required runtime payload entry is missing.
- the package database connection cannot be opened.
- the configured database driver is not MySQL/MariaDB.
- `pdo_mysql` is unavailable for MySQL/MariaDB.
- InnoDB is known to be unavailable.
- the detected Pincore version is older than 3.14.0.

Warnings are emitted for low-but-usable memory, small HTTP upload limits, short execution windows, database versions below the tested production baseline, non-`utf8mb4` database defaults, unavailable OPcache, unavailable GD/Imagick, or an InnoDB capability probe that the database account is not permitted to inspect.

## Common failures and recovery

| Symptom / code | Meaning | Correct recovery |
|---|---|---|
| Pinoox reports minimum kernel/minpin failure | Pincore is older than 3.14.0 / code 232 | Upgrade Pincore. Do not bypass the check with a force install. |
| `install.php_extensions_missing` | One or more PHP extensions are unavailable | Enable the listed extensions in the hosting control panel/PHP selector, then retry the same PINX. |
| `install.pdo_mysql_missing` | MySQL is configured but PDO MySQL is absent | Enable `pdo_mysql`, then retry. |
| `install.php_memory_critical` | PHP memory limit is below 32 MiB | Raise the PHP memory limit before retrying. 128 MiB+ is recommended. |
| PINX upload is rejected before installation | Web-server/PHP upload or request limit is too low | Raise `upload_max_filesize` and `post_max_size`, or use an authorized native CLI installation path where available. |
| `install.package_root_not_writable` / `install.package_parent_not_writable` | Pinoox cannot safely maintain the app directory | Correct owner/group/permissions using least privilege. Do not use blanket `0777` as a permanent fix. |
| `install.temp_not_writable` | PHP cannot use its temporary directory | Correct `upload_tmp_dir`/system temp configuration and permissions. |
| `install.disk_space_low` | Recovery/update does not have enough filesystem headroom | Free disk space before retrying. |
| `install.database_unavailable` | Database connection failed | Correct host/port/database/user credentials or hosting DB availability, then retry. |
| `install.database_driver_unsupported` | Current package connection is not MySQL/MariaDB | Use a supported MySQL/MariaDB Pinoox connection for this release. |
| `install.database_innodb_unavailable` | Required table engine is unavailable | Enable/use InnoDB-capable MySQL/MariaDB service before retrying. |
| `install.database_charset_not_utf8mb4` warning | DB default is not full-Unicode | Prefer a `utf8mb4` database/collation before production content is created. |
| uninstall refuses with remaining owned tables | Full package rollback did not reach a safe state | Keep the app files in place, inspect Pinoox migration history/recovery diagnostics, and do not manually drop the tables. |

## Partial extraction and failed installation

Pincore validates `minpin` before extraction, but some failures can still occur after files have been extracted. Pincore does not guarantee that every late installation failure automatically restores the previous filesystem state.

NanoPino reduces that risk by placing its environment preflight before all CMS schema-creating migrations. If this preflight fails, fix the reported environment problem and retry the same PINX.

If `apps/com_pinoox_cms` exists after a failed first install:

- first determine whether NanoPino migration history or `cms_*` tables exist;
- if there is no NanoPino migration history and no NanoPino schema, an orphaned extracted directory can be moved aside under a backup name before a clean reinstall;
- if migration history or CMS tables exist, do **not** manually delete the application directory or tables. Use the native uninstall/recovery flow and retain a database/filesystem backup.

## Normal update policy

A normal upgrade must not require `--force`. R15 CI installs the previous official 0.23.28 package and then installs 0.23.29 through the native update path without force.

Force is reserved for controlled recovery/testing cases where the operator understands the consequences. It is not the normal update procedure.

## Artifact integrity gate

Every release PINX built through `tools/release/build-pinx.sh` must pass `tools/release/verify-pinx-installability.php`.

The gate verifies:

- PINX/ZIP readability and bounded archive size/entry count;
- safe paths with no traversal or duplicate entries;
- package/version/version-code/minpin parity;
- exact `payload/app.php` and `payload/lifecycle.php` parity with verified source;
- release metadata and app SHA-256 parity;
- presence and validity of the Vite manifest and build evidence;
- presence and ordering of the installability preflight migration;
- required runtime files;
- absence of source tests, Node dependencies and development-only Admin build scripts.

A successful native `pinx:build` is therefore necessary but not sufficient for release.

## Post-install validation

After installation/update, verify at minimum:

1. the installed version is 0.23.29 / code 2329;
2. the NanoPino admin route opens and production Vite assets load;
3. Settings and Security pages open without runtime errors;
4. System Health can execute;
5. all 16 required CMS tables exist;
6. there is exactly one successful record for the R15 installability preflight migration;
7. Safe Mode is not unexpectedly active;
8. there are no new active critical errors in NanoPino diagnostics.

Repository CI proves native package lifecycle behavior in its controlled environment. Signed-PINX trust-chain verification, browser/mobile E2E, target shared-hosting validation and fault-injected recovery remain separate Stable release gates.
