# Compatibility Matrix

| Component | Current contract |
|---|---|
| NanoPino | 0.23.73 / code 2373 |
| Platform identity | NanoShell / nanoshell-platform-v1 |
| Package | com_pinoox_cms |
| PHP | >= 8.2 |
| Pincore | >= 3.14.0 |
| Native minpin | 232 |
| Luma | >= 0.4.10 |
| Database | MySQL/MariaDB package connection |
| Historical lifecycle database | MySQL 8.4 |
| Historical Pincore lifecycle matrix | 3.14.0, 3.14.4 |
| Admin | Vue + Luma |
| Direction | RTL supported |
| Hosting | shared-hosting compatible baseline |
| Worker | not required for baseline queue mode |

Pincore 3.8.15/code 205 and 3.10.0/code 216 are no longer valid NanoPino baselines for the current Pinoox 3.4 platform pair. R15 requires the current platform installer/runtime contract verified from Pincore 3.14.0/code 232 upward.

R15 repository CI verifies a native 0.23.28 → 0.23.29 update without force, a clean 0.23.29 fresh install and uninstall, and database table invariants on the declared Pincore matrix. Those are historical lifecycle scenarios; the current 0.23.73 candidate still requires the target-environment gates listed in the release documentation.

The database compatibility contract accepts MySQL/MariaDB package connections. R15 CI specifically tests MySQL 8.4; exact MariaDB versions and shared-hosting restrictions still require target-environment evidence.

See [Installation Readiness and Recovery](../deployment/installability.md) for PHP extensions, filesystem, upload, database and recovery requirements.
