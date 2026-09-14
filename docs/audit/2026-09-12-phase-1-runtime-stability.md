# Phase 1 — Runtime Stability and API Truth

## Scope

This phase hardens the CMS request boundary without changing the Pinoox route
contract, database schema, authentication provider, or public API paths.

## Changes

- Authorization failures thrown before a Facade response are mapped to HTTP
  `403 FORBIDDEN` instead of being reported as HTTP 500.
- Error responses receive a correlation id so an administrator can match a
  visible error with the structured CMS log.
- Non-database failures no longer trigger nine database/schema probes while the
  error boundary is already handling the failure.
- The browser client now requires the explicit `{ success: true, data: ... }`
  CMS response envelope. Empty or ambiguous JSON cannot acknowledge an
  operation.
- Native runtime API requests send a correlation id for reads as well as
  mutations.

## Validation

- PHP runtime contracts: `61 passed, 0 failed`.
- Admin runtime tests: `170 passed, 0 failed`.
- Production Vite build and source/dist parity: passed.
- PHP syntax and `git diff --check`: passed.

## Remaining phase gates

These checks require a configured authenticated Pinoox/MySQL target and were
not claimed as completed in this local phase:

- Authenticated API + database integration tests with real users and roles.
- Browser E2E across login, permission denial, CRUD, media, settings, and
  mounted error responses.
- Fresh-install, upgrade, backup/restore, and fault-injection validation on
  the target deployment.
