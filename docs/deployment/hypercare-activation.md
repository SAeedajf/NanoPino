# On-call, monitoring and hypercare activation

This runbook activates the source-side operational bundle for NanoPino. It
does not grant deployment access, assign a human operator or send alerts by
itself. Target activation is complete only after the owner, scheduler and
alert destination are recorded in the release evidence.

## Activation contract

The machine-readable contract is
`resources/release/hypercare-activation-phase22-v1.json`. The current source
bundle contains:

- a GET-only monitor at `tools/live/monitor-nanopino-hypercare.sh`;
- four bounded target probes: Manager, public site, private dashboard and
  private health;
- required public security-header checks;
- JSONL snapshots suitable for an approved scheduler;
- non-zero exit status on any failed snapshot;
- a 48-hour hypercare default, with a 60-second monitoring cadence;
- explicit separation between source readiness and real target activation.

No credentials, customer data or private keys belong in the monitor or its
evidence file.

## Operator activation

Before enabling the monitor, record the release hash, target URL, primary
on-call owner, secondary owner, escalation destination and hypercare start
time in the change/incident system. Then run a one-snapshot smoke check:

```bash
NANOPINO_TARGET_URL=https://test.boxpdf.ir \
NANOPINO_MONITOR_OUTPUT=/var/log/nanopino/hypercare.jsonl \
tools/live/monitor-nanopino-hypercare.sh
```

The scheduler may repeat the command, but it must own alert delivery and
deduplication. A failed exit status opens an incident; it is not proof that a
rollback is safe. A rollback decision must use the signed artifact, approved
backup and the rollback runbook.

For a bounded Canary observation, use at least 20 samples and a five-minute
window. The following example assumes a 15-second cadence and is intentionally
operator-triggered:

```bash
NANOPINO_TARGET_URL=https://test.boxpdf.ir \
NANOPINO_MONITOR_SAMPLES=20 \
NANOPINO_MONITOR_INTERVAL_SECONDS=15 \
NANOPINO_MONITOR_OUTPUT=/var/log/nanopino/canary.jsonl \
tools/live/monitor-nanopino-hypercare.sh
```

This monitor intentionally does not perform authenticated checks. Those need
a target-owned test account, a protected secret store and an explicit access
record. It also does not probe queue workers, database mutations, backups or
customer workflows.

## Incident policy

Open or escalate an incident for a failed health/security-boundary probe, a
critical customer-visible error, an unexplained data-integrity signal or a
material error/latency regression. Preserve the UTC timestamp, release hash,
snapshot lines, operator, affected route and decision. Roll back immediately
for a critical incident or failed recovery boundary when the approved
rollback criteria are met.

Suggested ownership:

| Severity | First response | Escalation | Action |
| --- | --- | --- | --- |
| S0 | primary on-call immediately | secondary and release owner | stop promotion; assess rollback |
| S1 | primary within 15 minutes | secondary if unresolved | contain, investigate and communicate |
| S2 | next business window | product/support owner | ticket, trend and schedule fix |

## Hypercare close-out

Do not close hypercare merely because the monitor is green. The release owner
must record the final health snapshot, all open incidents, rollback
disposition, customer communication status and the preserved artifact hash.
Until those fields and the target monitoring/alert ownership are recorded,
`post_release_support_ready` remains false and Stable promotion remains
blocked.
