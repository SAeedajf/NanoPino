# Phase 17 — Recovery, Safe Mode and Fault Injection

## Outcome

Phase 17 hardens the recovery boundary around native Pinoox extension
operations. Recovery and Safe Mode now fail closed when persisted state is
corrupt, Safe Mode only permits explicitly core packages to boot, and direct
recovery actions enable Safe Mode when restoration is incomplete.

The native Pinoox installer remains the mutation owner. Fault injection is an
explicit dependency seam used by tests; it is not configured from HTTP,
request options or production environment input. Normal runtime services use
`NullFaultInjector`.

## Recovery guarantees

- Recovery points remain atomically persisted and provider receipts are
  restored in reverse provider order.
- Provider failures leave the point in `failed` state with an actionable
  recovery identity instead of pretending that restore succeeded.
- Direct Recovery API restore failures now enable Safe Mode and associate the
  failed extension and recovery point before returning the failure.
- Existing filesystem integrity, symbolic-link rejection and migration batch
  boundary protections remain active.

## Safe Mode guarantees

- Malformed, unreadable, unsupported or symbolic-link state files produce an
  enabled fail-closed state requiring manual recovery.
- Safe Mode boot policy permits explicitly designated core packages only;
  third-party packages are skipped even if they are not in the quarantine
  list. The quarantine list remains diagnostic and identifies the triggering
  extension.
- Activation through the Extension Center follows the same Safe Mode boundary.
- Safe Mode exit still requires authorization and a successful runtime health
  gate; restore does not silently disable Safe Mode.

## Fault-injection matrix

The deterministic test injector supports bounded, named checkpoints including:

- `install.step.*`, `install.commit.before` and install rollback points;
- `update.step.*`, `update.commit.before` and update rollback points;
- `recovery.create.*`, `recovery.restore.*` and recovery delete points;
- `safe_mode.persist.before` and `safe_mode.persist.after_write`;
- native install/update and uninstall checkpoints immediately after the native
  mutation and after runtime rebuild.

These points allow tests to prove both sides of the failure boundary:
successful compensation returns to the previous lifecycle state, while an
incomplete compensation persists Safe Mode and quarantine information.

## Verification

- PHP CMS runtime suite: **72/72 PASS**.
- Frontend contract suite: **204/204 PASS**.
- PHP syntax checks for all changed recovery, coordinator and executor files:
  **PASS**.
- Target mutation: **not executed**. The target was not used for fault
  injection, rollback, Safe Mode activation or package lifecycle changes.

## Release decision

Phase 17 is complete in the local source boundary. The target-runtime gates
remain open: real boot-order integration, authorized fault-injected native
filesystem/database recovery, signed lifecycle rehearsal and authenticated
browser E2E still require target access and rollback authority.
