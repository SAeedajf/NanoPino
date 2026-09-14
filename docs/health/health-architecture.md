# Health Architecture

Health checks are registered in the kernel and executed through `HealthRunner`. Core checks cover runtime/platform concerns such as PHP, memory, disk, database, cache/storage/queue/scheduler/extensions and kernel compatibility where registered.

System Health API exposes the current snapshot. Health History persists snapshots to the CMS storage root and provides a bounded recent-history endpoint.

Health is used by recovery/Safe Mode decisions and should reflect real runtime probes, not static feature declarations.

The `system.cms_integrity` check runs `CmsSchemaIntegrityProbe`. It verifies the
internal relation inventory, performs bounded orphan existence probes, and
reports missing database foreign keys without attempting a repair. A warning is
expected on the current baseline because the canonical migrations do not yet
declare those foreign keys; an error means an orphan relation was detected or
the schema is incomplete.
