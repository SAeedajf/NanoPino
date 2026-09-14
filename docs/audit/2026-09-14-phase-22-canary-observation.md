# Phase 22 — Canary observation and Stable decision

## Observation record

On 2026-09-14, the safe GET-only monitor ran against
`https://test.boxpdf.ir` from `2026-09-14T09:56:37Z` through
`2026-09-14T10:03:03Z`.

| Signal | Result |
| --- | --- |
| Samples | 20 |
| Window | 386 seconds |
| Failed samples | 0 |
| Availability | 100% |
| Error rate | 0% |
| Critical incidents | 0 |
| Security-header checks | 20/20 passed |
| Probe requests | 80 |
| Probe p95 latency | 1992.764ms |
| Policy limit | 1200ms |

The complete machine-readable record is
`resources/release/canary-observation-phase22-v1.json`.

## Decision

The observation passed availability and security-boundary checks, but it is
**not promotable**. The measured probe p95 exceeds the 1200ms Canary limit,
and the signed candidate was not installed or traffic-sliced on the target.
This is therefore a read-only target observation, not evidence of a deployed
candidate Canary.

Stable approval is **not granted**. The decision remains fail-closed with
these blockers:

- `canary.deployment_not_verified`
- `canary.p95_latency_exceeded`
- `stable.approval_required`

Only the named release owner may record explicit Stable approval after the
candidate is deployed, authenticated target monitoring is enabled, the p95
latency threshold is met and the remaining release gates are evidenced.
