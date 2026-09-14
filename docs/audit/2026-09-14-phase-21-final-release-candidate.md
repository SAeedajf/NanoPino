# Phase 21 — Final Release Candidate

## Outcome

Phase 21 assembles the final local Release Candidate from the current
NanoPino/Pinoox source and re-runs the complete repository, package and
read-only target verification chain. The candidate is technically coherent
and distributable as an unsigned PINX, but is not declared Stable because the
target and signed-production gates remain open.

## RC identity

| Field | Value |
|---|---|
| Product | NanoPino |
| Package | `com_pinoox_cms` |
| Version | `0.23.72 / code 2372` |
| Minimum Pincore | `3.14.0 / code 232` |
| Artifact | `NanoPino-0.23.72-phase21-final-release-candidate.pinx` |
| Signature | Unsigned local candidate; signing not executed |

## Final verification

| Gate | Result | Evidence |
|---|---|---|
| Frontend contract suite | PASS — 223/223 | `theme/cms-admin/tests/*.test.js` |
| PHP runtime suite | PASS — 72/72 | `tests/php/run.php` |
| Documentation audit | PASS — 31 required, 23 machine contracts, 63 Markdown, 34 internal links | `DocumentationAuditService` |
| Production Vite build | PASS — 62 reachable assets | `theme/cms-admin` build tooling |
| Admin source/dist parity | PASS | source fingerprint matches `.cms-build.json` |
| Direct Admin runtime parity | PASS | runtime fingerprint matches `admin-runtime-v1.json` |
| Release metadata parity | PASS | `release-metadata-v1.json` matches `app.php` |
| Security preflight | PASS — 0 high, 2 warnings | final PINX preflight |
| PINX archive integrity | PASS | `unzip -t`, `pinx:info`, package entry checks |
| Target public read-only smoke | PASS | `/qwe/site` HTTP 200 with CSP/security headers |
| Target private boundary | PASS | `/qwe/` HTTP 401 without session |
| Target mutation/deployment | NOT EXECUTED | no credentials, migration, install or update used |

The two security warnings are the already-reviewed `dynamic_include` signal in
the native GlobalBlock service and the guarded remote-search network primitive.
Neither is a high-risk preflight finding, and both remain visible in the Phase
20 threat register.

## Release gate decision

The local technical candidate gate is **PASS**. The production release gate is
**BLOCKED** by the 10 declared target/Stable blockers in
`resources/docs/documentation-manifest-v1.json`. This is an intentional
distinction: local source/build/package integrity does not prove authenticated
target behavior, signed trust-chain behavior or production operations.

Open evidence includes target Pinoox integration and database lifecycle,
authenticated Admin/WCAG browser E2E, signed PINX lifecycle, persistent Theme
activation, Safe Mode boot-order recovery, deployment health, production
security/performance measurements and the Admin i18n public contract.

Machine-readable evidence: `resources/release/release-candidate-phase21-v1.json`.
