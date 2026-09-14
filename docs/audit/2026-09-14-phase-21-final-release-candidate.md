# Phase 21 — Final Release Candidate

## Outcome

Phase 21 assembles the final local Release Candidate from the current
NanoPino/Pinoox source and re-runs the complete repository, package and
read-only target verification chain. The candidate is technically coherent
and distributable as a signed PINX. The target update and Manager App View now
pass on the live test host; Stable remains blocked by the remaining production
and operations gates.

## RC identity

| Field | Value |
|---|---|
| Product | NanoPino |
| Package | `com_pinoox_cms` |
| Version | `0.23.74 / code 2374` |
| Minimum Pincore | `3.14.0 / code 232` |
| Artifact | `NanoPino-0.23.74-csp-nonce-fix.pinx` |
| Artifact size | `2,128,924 bytes` |
| Artifact SHA256 | `fcc20e33a97e5e0933dfc02355b4d8c166b9928c081bad4a2930b351f2568b57` |
| Signature | Ed25519; key `nanopino-local:main`; fingerprint `bae3db064a68d3ad` |

## Final verification

| Gate | Result | Evidence |
|---|---|---|
| Frontend contract suite | PASS — 235/235 | `theme/cms-admin/tests/*.test.js` |
| PHP runtime suite | PASS — 35/35 | `tests/php/run.php` |
| Documentation audit | PASS — 31 required, 23 machine contracts, 63 Markdown, 34 internal links | `DocumentationAuditService` |
| Production Vite build | PASS — 62 reachable assets | `theme/cms-admin` build tooling |
| Admin source/dist parity | PASS | source fingerprint matches `.cms-build.json` |
| Direct Admin runtime parity | PASS | runtime fingerprint matches `admin-runtime-v1.json` |
| Release metadata parity | PASS | `release-metadata-v1.json` matches `app.php` |
| Security preflight | PASS — 0 high, 2 warnings | final PINX preflight |
| PINX archive integrity | PASS | `pinx:info`, installability preflight, and native `PinxVerifier` with `require_signature` |
| Target public read-only smoke | PASS | `/qwe/site` HTTP 200 with CSP/security headers |
| Target private boundary | PASS | `/qwe/` HTTP 401 without session |
| Target mutation/deployment | PASS | target accepted signed `0.23.74`; Manager reports update success and the installed dashboard is active |
| Target Manager App View | PASS | Dashboard, navigation, health cards and content/plugin summaries render inside the iframe |
| Target CSP nonce boundary | PASS | HTTP 200, `frame-ancestors 'self'`, `SAMEORIGIN`, and HTML/CSP nonce values match |

The two security warnings are the already-reviewed `dynamic_include` signal in
the native GlobalBlock service and the guarded remote-search network primitive.
Neither is a high-risk preflight finding, and both remain visible in the Phase
20 threat register.

## Release gate decision

The local technical candidate gate is **PASS**. The production release gate is
**BLOCKED** by the 9 remaining declared target/Stable blockers in
`resources/docs/documentation-manifest-v1.json`. This is an intentional
distinction: local source/build/package integrity does not prove authenticated
target behavior, signed trust-chain behavior or production operations.

Open evidence includes target database lifecycle, authenticated browser/WCAG
matrix E2E, signed trust-chain lifecycle evidence beyond installer acceptance,
persistent Theme activation, Safe Mode boot-order recovery, deployment health,
production security/performance measurements and the Admin i18n public
contract. The first target attempt exposed and fixed a canonical payload-hash
ordering defect in the release signing path. The next live attempt exposed a
request/response CSP nonce mismatch that blocked the inline bootstrap; the
corrected artifact passed the native verifier, was accepted by the target, and
rendered the authenticated Manager App View successfully.

Machine-readable evidence: `resources/release/release-candidate-phase21-v1.json`.
