# ADR-001: Acknowledge request snapshots without discarding newer editor state

Date: 2026-09-06. Status: accepted for the audit correction branch.

## Context

Settings and Builder have editable state while requests are pending. Full reloads and unconditional dirty resets acknowledge values that were never sent, or discard unrelated edits. Content creation and publication are separate commits; retries must preserve the successful first operation. Canonical Vue and directly served runtime modules currently duplicate these flows.

## Decision

Capture the submitted document/value before awaiting an API request. A successful response advances persisted metadata immediately. Replace the local value only if it still matches the submitted snapshot; otherwise keep the newer draft dirty. Settings acknowledge each successful batch item independently. Content retains the created record ID before publication, and bulk operations capture the confirmed action and IDs once. Pointer cancellation never commits a drop. Keep both existing renderer implementations aligned and test the actual methods using delayed/failing API responses.

## Alternatives

Disabling all editing during every request avoids some races but unnecessarily blocks drafting and does not solve partial-batch retries. Reloading the whole collection is simpler but destroys local state. A single shared state module is preferable long-term but requires a broader runtime delivery-contract migration; it is deferred rather than mixed into this repair.

## Consequences

Users may retain unsaved edits after a save or publish of an earlier snapshot. The server version can advance while local dirty remains true. A subsequent explicit save submits those newer edits using the acknowledged version. These client fixes do not provide backend idempotency for a lost response, database transactionality across a batch, or conflict-free concurrent editing.

## Compatibility and verification

No backend route, schema, package ID, Pincore or vendor changes. Rebuild canonical Vite assets and refresh runtime evidence. Behavioral tests cover delayed save/publish, partial settings writes, invalid JSON, cancelled drag, content retry, and bulk action stability. Authenticated browser and DB tests remain release gates.
