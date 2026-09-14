# Phase 14 — Media, Storage, Cache and Queue

## Outcome

Phase 14 is source-verified and locally integrated. The existing native Pinoox
media and storage boundaries were preserved; the missing runtime connection for
the durable file queue was added; semantic cache invalidation now follows CMS
content mutations; and public site/page rendering uses a short-lived,
site-scoped semantic page cache.

This is not a claim that the exact `test.boxpdf.ir` deployment has been
updated or that its production disk, cache backend, cron runner and native file
permissions have been exercised. No target mutation was performed.

## Media

- Uploads remain behind `MediaUploadValidator` and `MediaUploadPolicy`.
- Server-derived MIME, decoded image type, extension, size, hash and symlink
  checks are retained.
- Native storage continues through Pinoox `UploadBuilder`; CMS metadata is
  compensated by removing the native file if persistence fails.
- Delete remains usage-aware: an asset in use is rejected, metadata is
  soft-deleted first, and native deletion failure restores the metadata state.
- Media repository migrations retain native file identity, variants, usage
  tracking, status filtering and the site/status read index.

## Storage

- `CmsStorage` now has an explicit runtime accessor backed by the native
  `PinooxStorageDriver`.
- Object keys are site/domain scoped and reject traversal, absolute paths,
  NUL bytes, empty segments and oversized segments.
- The in-memory adapter remains available for deterministic isolated tests.
- Storage health remains a real write/read/delete probe and is not a synthetic
  “available” flag.

## Cache

- `SemanticCache` is now a singleton runtime boundary, preventing separate
  request services from using unrelated tag-generation clocks.
- Public site home and published page rendering use the Page layer with a
  60-second TTL and site-scoped content tags.
- Content create, update, status transitions, scheduled publication and term
  assignment invalidate all semantic layers and content/site/type/locale/id
  tags.
- Invalidation is best-effort by design: a cache failure cannot roll back a
  durable content write. The next read repopulates the cache.
- Public HTTP responses retain `no-cache`; the semantic cache is an internal
  application optimization, not an unverified CDN/browser cache contract.

## Queue

- Core `content.publish_due` is registered in the kernel queue registry.
- Runtime exposes the durable `FileQueueRepository`, `QueueWorker` and
  `QueueDispatcher`; configured Auto mode selects Async when native scheduler
  primitives are available.
- Search queue jobs are registered lazily at the runtime queue boundary, so
  remote search configuration is not required during basic kernel construction.
- Native scheduler now registers `cms.queue.drain` every minute with
  `withoutOverlapping`; queue state remains durable JSON with locking, retry,
  stale-processing recovery, dead-letter and operator retry semantics.
- The existing direct editorial scheduler remains separate and is preserved;
  it is compare-and-set and therefore does not double-publish when queue drain
  runs in the same cadence.

## Verification

- PHP CMS runtime suite: **67/67 PASS**.
- Frontend contract suite: **203/203 PASS**.
- Production Vite build: **PASS**, 62 reachable assets and source/runtime
  parity verified.
- Native local scheduler listing: `cms.queue.drain` and
  `cms.content.publish-due`, both `* * * * *`, callback and locked.
- Phase 14 isolated contracts: durable async dispatch/dedup/exactly-once
  completion, content-tag invalidation, and scoped storage/traversal rejection
  all pass.
- Target live status: unchanged and not mutated; candidate deployment,
  authenticated E2E, production queue execution and native media/storage
  permission checks remain pending. The current read-only target probe returned
  `401` for the private dashboard and health route, which matches the canonical
  unauthenticated boundary; the probe also accepts `403` for older target
  deployments.

## Release decision

The source candidate remains **0.23.72 / code 2372**. Phase 14 is complete in
the local source boundary. Stable release remains blocked by the pre-existing
target deployment, signed PINX lifecycle, authenticated browser/WCAG,
production security/performance, target database and native recovery evidence
requirements.
