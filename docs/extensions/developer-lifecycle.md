# Extension Developer Lifecycle

The operational lifecycle is:

**upload → inspect → trust/compatibility → dependencies → permissions → recovery snapshot → staging/migration/install → health → activate**

Update follows the same controlled path. Uninstall and rollback are handled through the extension operation coordinator and recovery subsystem.

R11 removes the unsafe assumption that the count of new migration files equals the number of rollback batches. Package migration recovery now records migration history and only rolls back newly added contiguous batches. If recovery is incomplete, a non-core app is disabled where possible and Safe Mode is enabled.

This is migration-state compensation, not a full byte-for-byte database backup.
