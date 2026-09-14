# Runtime API

Base: `/api/v1/cms`.

The authoritative public route set is the combination of:
- `payload/Cms/Runtime/CmsRuntimeApiManifest.php`
- JSON contracts under `payload/resources/api/*.json`

Repository tests require bidirectional coverage: every public runtime route must exist in machine contracts and every contract route must exist at runtime, including matching capability/rate-limit metadata where declared.

Mutation routes use the NanoPino/Pinoox security pipeline including capability checks, rate limits and CSRF integrity where applicable.

All runtime controllers, including system health, logs and support diagnostics, return the same response envelope:

```json
{"success": true, "data": {}}
```

Errors use `success: false` with an `error` object. CMS responses are marked `Cache-Control: no-store` because admin and diagnostics payloads may contain private operational state.


## Authoring support APIs

The Admin authoring surfaces consume versioned runtime APIs instead of hard-coded identifiers.

- `GET /api/v1/cms/taxonomies/{key}/terms` searches registered taxonomy terms by site, locale and optional query. Authorization is enforced both by the runtime route and the registered Taxonomy capability.
- `POST`, `PATCH`, and `DELETE /api/v1/cms/taxonomies/{key}/terms[/{id}]` provide authorized taxonomy management. Deletes return a conflict when a term is assigned to content or still has children.
- `GET /api/v1/cms/content/{id}/revisions/{revisionId}` returns an integrity-verified revision snapshot for preview/compare before restore.
- Content relation and parent pickers reuse `GET /content` with the list projection and pagination.
- Media and gallery pickers reuse the Media API. No second media catalog or storage path is introduced.

The machine-readable contracts remain authoritative in `payload/resources/api/*.json`.

Public taxonomy archives use the path `/{taxonomy}/{termSlug}` and are separate from the authenticated API. They resolve only public taxonomy definitions and published content assigned through `content_terms`.
