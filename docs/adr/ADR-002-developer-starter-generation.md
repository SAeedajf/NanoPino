# ADR-002: Generate Extension starters through a bounded Admin API

Date: 2026-09-07. Status: accepted for P3 Extension/Update/Recovery remediation.

## Context

The Developer SDK page advertised Starter Packages but exposed no executable source or download path. NanoPino already owns `ExtensionPackageBlueprint` and `ExtensionScaffoldGenerator`, while shared-hosting users may not have SSH. Adding a second package format or a Core-specific loader would violate the Pinoox-native extension boundary.

## Decision

Expose `POST /api/v1/cms/developer/starter` as a versioned, CSRF-protected, rate-limited Admin API guarded by `system.developer.generate`. The endpoint reuses the existing SDK blueprint/scaffold generator and never generates `core-module` packages.

Generated source is isolated under the CMS storage workspace, bounded to 1 MiB of source and 2 MiB of optional ZIP output, hashed before return, then the temporary workspace is deleted. When PHP `ZipArchive` is unavailable, the API still returns the generated source files so shared-hosting users are not blocked.

The generator produces source only. Final install/update continues through official Pinoox PINX transport and Extension Center Inspect → Review → controlled lifecycle. It does not install, activate, mutate Core, edit Pincore/vendor, or create a parallel loader.

## Consequences

Developer onboarding becomes executable from Admin without SSH, while package installation remains separated from source generation. A new capability must be granted explicitly for generation. Source responses are intentionally small and generated from templates; this endpoint is not a general filesystem download API.

Generated Admin example pages are labeled as development/contract examples and include human route metadata, preventing technical names such as `example.page` from appearing as a finished product surface.

## Verification

Machine contract: `payload/resources/api/developer-v1.json`. Runtime route coverage is bidirectional with the API manifest. P3 regression tests verify capability gating, size limits, Core Module rejection, human example metadata, and Admin download wiring. Production Vite build and PINX lifecycle remain release gates.
