# ADR-002 — Validate and normalize the mounted CMS API boundary

Date: 2026-09-10. Status: accepted for the audit correction branch.

## Context

NanoPino registers a versioned API manifest through the native Pinoox app
lifecycle. A malformed action reference, unregistered capability, duplicate
route, unconstrained placeholder, or missing mutation flow can otherwise fail
later in the router with an opaque runtime error. The native permission flow
can also return a non-envelope denial response when the API is mounted below an
app path such as `/nano/api/...`.

## Decision

Validate the complete executable manifest immediately before API registration.
The validator checks route identity, controller inheritance, public instance
action methods, capability registration, rate-limit/CSRF flow, and placeholder
filters. At the response boundary, only mounted CMS API 401/403 responses that
are not already in the CMS envelope are normalized to the standard
`success/error` shape. Existing enveloped responses remain unchanged.

## Consequences

Package or route wiring mistakes fail early and are easier to diagnose. API
clients receive a stable error shape even when denial happens before the CMS
controller. The response normalizer does not grant access, replace native
authentication, or hide target-runtime failures; event dispatch and
authenticated behavior still require target E2E validation.

## Compatibility and verification

The public API remains `/api/v1/cms` with the existing route names, methods,
permissions, and native Pinoox lifecycle. The CMS PHP contract suite covers
the full manifest, invalid action/duplicate route rejection, mounted denial
normalization, and preservation of an existing envelope. Target-host
authenticated API validation remains a release gate.
