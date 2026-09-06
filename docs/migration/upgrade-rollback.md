# Upgrade and Rollback

Before extension install/update, NanoPino creates a Recovery Point. For Pinoox app packages R11 captures filesystem state and the package migration-history baseline.

On failure, migration compensation only rolls back batches that are newly added after the snapshot, newest, contiguous and free of baseline migrations. After rollback, migration history must exactly match the captured baseline.

This replaces the unsafe assumption that the count of new migration files equals the number of rollback batches.

This mechanism still does not restore arbitrary data mutations or partially committed DDL that has no recoverable migration history.
