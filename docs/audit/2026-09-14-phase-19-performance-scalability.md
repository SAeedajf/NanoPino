# Phase 19 — Performance and Scalability

## Outcome

Phase 19 adds bounded performance controls and removes two concrete sources of
avoidable request amplification. The changes stay inside the native CMS
repository and Admin layers, preserve the API envelope and data model, and do
not mutate the target installation.

## Changes

- The canonical Content management collection now requests the scalar `list`
  projection explicitly. List reads therefore do not materialize document
  payloads or field/relation/taxonomy associations; detail and editor reads
  remain explicit and unchanged.
- Scheduled publication keeps its per-record compare-and-set claim, but
  collects successful IDs and performs one bounded fresh-row query followed by
  one batch detail hydration. This removes the former per-record `find` plus
  three association-query pattern while preserving deterministic scheduler
  ordering and exactly-once claim semantics.
- The Admin production build now runs `performance-budget.mjs`. The budget
  fails the build if raw JavaScript exceeds 2,200,000 bytes, raw CSS exceeds
  300,000 bytes, the largest JavaScript asset exceeds 750,000 bytes, or the
  largest CSS asset exceeds 150,000 bytes.
- Existing backend telemetry remains the measurement boundary: DB query count
  and time, slow-query and N+1 detection, cache ratio, queue timing, memory,
  extension boot cost and core performance budgets are exposed through the
  native Performance service.

## Verification

| Check | Result | Evidence |
|---|---|---|
| Frontend contract suite | PASS — 212/212 | `theme/cms-admin/tests/*.test.js` |
| PHP CMS runtime suite | PASS — 72/72 | `tests/php/run.php` |
| PHP syntax | PASS | `PinooxContentRepository.php` |
| Vite production build | PASS | 62 reachable assets; source/dist parity verified |
| Raw JavaScript budget | PASS — 1,858,212 / 2,200,000 bytes | `performance-budget.mjs` |
| Raw CSS budget | PASS — 221,991 / 300,000 bytes | `performance-budget.mjs` |
| Largest JS asset | PASS — 676,324 / 750,000 bytes | `vendor-icons-*.js` |
| Largest CSS asset | PASS — 126,609 / 150,000 bytes | `main-*.css` |
| Benchmark runner smoke | PASS — synthetic p95 0.000216 ms | `BenchmarkRunner`, not a production workload benchmark |
| Target mutation | NOT EXECUTED | no credentials, migration, deployment or write used |

The asset values are raw on-disk bytes, not compressed transfer measurements.
The synthetic benchmark only proves the benchmark runner boundary and must not
be interpreted as CMS request latency. Production p50/p95 values still require
an authenticated target workload and database/cache/queue instrumentation.

## Scalability boundary still open

- The Content list API still returns a total count by default, which is useful
  for the current Admin pagination but adds a count query per request at large
  scale. An opt-out total contract should be designed and measured before
  changing that public behavior.
- Offset pagination remains bounded but is less efficient on very large result
  sets; the API already exposes `before_id` cursor support, which should become
  the preferred Admin path after UX and contract measurement.
- The largest JavaScript chunk is the shared icon vendor at 676 KB raw. It is
  within the new budget, but further icon-level tree-shaking or route-scoped
  loading is a future optimization rather than a claim of completion here.
- Real target latency, OPcache state, database indexes, queue worker cadence,
  cache hit ratio and concurrent-load behavior remain unverified because the
  target Admin session was not available for this phase.

## Gate

Phase 19 is **source, contract, build and budget verified**. It does not make
the release Stable. Target authenticated performance measurements, load
testing, database/cache/queue production evidence, signed deployment and the
previous target lifecycle blockers remain required.

Machine-readable evidence: `resources/release/performance-scalability-phase19-v1.json`.
