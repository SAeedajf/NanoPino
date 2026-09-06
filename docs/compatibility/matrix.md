# Compatibility Matrix

| Component | Current contract |
|---|---|
| NanoPino | 0.23.29 / code 2329 |
| Package | com_pinoox_cms |
| PHP | >= 8.2 |
| Pincore | >= 3.10.0 |
| Native minpin | 216 |
| Luma | >= 0.4.10 |
| Database | MySQL/MariaDB package connection |
| CI database | MySQL 8.4 |
| CI Pincore matrix | 3.10.0, 3.14.4 |
| Admin | Vue + Luma |
| Direction | RTL supported |
| Hosting | shared-hosting compatible baseline |
| Worker | not required for baseline queue mode |

Pincore 3.8.15/code 205 is no longer a valid NanoPino baseline because the package now depends on the native application lifecycle API used for safe uninstall/recovery. That API is available from the supported Pincore 3.10.0/code 216 baseline.

R15 repository CI verifies a native 0.23.28 → 0.23.29 update without force, a clean 0.23.29 fresh install and uninstall, and database table invariants on the declared Pincore matrix.

The database compatibility contract accepts MySQL/MariaDB package connections. R15 CI specifically tests MySQL 8.4; exact MariaDB versions and shared-hosting restrictions still require target-environment evidence.

See [Installation Readiness and Recovery](../deployment/installability.md) for PHP extensions, filesystem, upload, database and recovery requirements.
