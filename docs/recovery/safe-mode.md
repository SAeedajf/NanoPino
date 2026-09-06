# Recovery and Safe Mode

Recovery Points are composed from snapshot providers and move through explicit states such as ready, restoring, restored or failed.

Filesystem recovery verifies snapshot integrity and swaps restored trees through a temporary location.

For extension operations, incomplete recovery is fail-closed. NanoPino attempts to disable the failed non-core Pinoox app and then enables Safe Mode with the extension and recovery-point identifiers.

Safe Mode exit is guarded by runtime health. Full boot-order protection is still a target-runtime release gate because NanoPino cannot guarantee it executes before every other Pinoox boot-global app.
