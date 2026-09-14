# Canary, Stable and post-release support runbook

This runbook is an operational checklist for the NanoPino release channels.
It is not an authorization to deploy or mutate a target.

## Before Canary

1. Confirm the exact package identity, version, source/dist/runtime parity and
   SHA-256 hash.
2. Require a signed PINX and a preflight result with zero High findings.
3. Capture an approved database/filesystem backup and verify the rollback
   artifact before opening traffic.
4. Confirm on-call ownership, monitoring, incident runbook, rollback runbook
   and customer communication readiness.
5. Start with a bounded traffic slice and record the start time, scope and
   correlation identifier.

## Canary observation

The promotion policy requires at least 300 seconds and 20 samples. Promote
only when error rate is at most 1%, availability is at least 99%, p95 latency
is at most 1200ms, there are no critical incidents, health checks pass and no
rollback request is active. Any missing or malformed evidence blocks promotion.

Rollback immediately on a critical incident, failed health check, explicit
rollback request, material error/latency regression or unexplained data
integrity signal. Record the decision, evidence and operator in the native
audit/logging path.

## Stable promotion

Stable requires all Canary gates, an explicitly closed Canary window, a
recorded Canary promotion and an explicit Stable approval. Stable promotion
must be a separate action; a successful Canary decision never implicitly
promotes Stable.

## Hypercare and support

Keep the on-call owner and monitoring active for the declared hypercare window.
Review health, errors, latency, queue/cache/storage signals, authentication
failures, public rendering and support incidents at a defined cadence. Publish
an incident timeline for every rollback or customer-visible degradation.

Close hypercare only after the owner records the final health snapshot, open
incidents, rollback disposition and customer communication status. Preserve
the release hash and evidence with the support record.

## Current NanoPino boundary

For Phase 22, signing, target installation, target migration, authenticated
Canary measurement and Stable approval remain pending. The current phase
artifact is therefore a local operational contract, not a production release.
