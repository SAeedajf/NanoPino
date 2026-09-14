# Phase 2 — Content Governance

> **Legacy numbering note:** this document belongs to an earlier internal
> roadmap where Content Governance was labelled Phase 2. The current product
> Phase 2 is Design System and Shell; use the canonical Phase Matrix for the
> current 1–8 mapping. The governance implementation remains part of the
> current content/editorial evidence.

## Scope

This phase hardens the existing Content, Workflow, Revision, Authorization and Audit layers. No new table was added: the existing `contents.status`, `content_revisions.kind` and audit event boundaries are sufficient for this incremental change.

## Delivered

- Added `pending_review`, `approved` and `archived` content states.
- Added explicit capabilities: `content.submit_review`, `content.approve` and `content.archive`.
- Added API operations:
  - `POST /api/v1/cms/content/{id}/submit-review`
  - `POST /api/v1/cms/content/{id}/approve`
  - `POST /api/v1/cms/content/{id}/archive`
- Added revision kinds for submitted, approved and archived snapshots.
- Editing content in `pending_review` or `approved` invalidates that decision and returns it to `draft`; published content keeps the existing live-edit compatibility contract.
- Added review/approval/archive actions, filters and bulk operations to the canonical Vue page and the runtime content center.
- Added Persian and English labels and revision-history labels.

## Workflow

```text
draft -> pending_review -> approved -> published -> archived
  |          |               |             |
  +-------> trash <----------+-------------+
```

Direct `draft -> published` and `draft -> scheduled` remain available to actors with `content.publish` so existing installations and trusted publisher roles do not break.

## Validation

- PHP unit/integration harness: executed after implementation.
- CMS frontend tests: executed after implementation.
- CMS frontend production build: executed after implementation.
- Live target deployment and browser verification: **Not executed in this phase**.

## Remaining risk

The review queue is status-based and does not yet provide reviewer comments, assignment, SLA, or a separate moderation dashboard. Those belong to a later editorial collaboration phase; the current API and capability boundaries are prepared for that extension.
