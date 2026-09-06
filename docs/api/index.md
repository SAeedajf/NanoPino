# Runtime API

Base: `/api/v1/cms`.

The authoritative public route set is the combination of:
- `payload/Cms/Runtime/CmsRuntimeApiManifest.php`
- JSON contracts under `payload/resources/api/*.json`

Repository tests require bidirectional coverage: every public runtime route must exist in machine contracts and every contract route must exist at runtime, including matching capability/rate-limit metadata where declared.

Mutation routes use the NanoPino/Pinoox security pipeline including capability checks, rate limits and CSRF integrity where applicable.
