# Database Schema Reference

NanoPino uses the Pinoox platform database connection and the CMS table prefix configured by the app. Database migrations in `database/migrations` are the authoritative schema definition for the checked-in package.

The package manifest currently declares 16 required CMS tables.

Use migration source and model/repository code when changing columns, indexes or relationships. MySQL/MariaDB identifier length and index constraints must be considered.

Extension recovery in R11 records package migration history and can compensate newly added contiguous migration batches. It is not a general database snapshot/restore facility.

`CmsSchemaIntegrityProbe` is a read-only health probe for the internal relation graph. It reports orphan relations as an error and missing foreign-key constraints as a warning; it never changes schema during boot or diagnostics. Constraint adoption requires a separate, data-scanned migration.
