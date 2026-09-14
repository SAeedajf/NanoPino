# Capability Selection — Public Navigation — 2026/09/11

## Decision

The highest-scoring next capability is a site-scoped public navigation menu.
It is now implemented by extending the existing Settings and PublicSite
rendering layers. No new database table or repository was justified.

## Evidence-based candidates

Scoring is 0–5. The weighted score uses structural fit 25%, safety/data
integrity 20%, maintainability 15%, performance/deployment fit 15%, CMS value
and extensibility 15%, and testability/risk 10%.

| Candidate | Fit | Safety | Maintainability | Perf/deploy | CMS value | Testability | Final |
|---|---:|---:|---:|---:|---:|---:|---:|
| Public navigation using existing Settings + renderers | 5 | 5 | 5 | 5 | 4 | 5 | **4.85** |
| Search full-text/cursor evolution | 4 | 4 | 4 | 5 | 4 | 3 | 4.05 |
| Full multi-site admin/catalog | 2 | 2 | 2 | 2 | 5 | 2 | 2.35 |
| Browser E2E/accessibility matrix | 3 | 5 | 4 | 3 | 2 | 3 | 3.65 |

The multi-site option was not selected because the current authorization
boundary and settings scope are explicitly single-site (`SingleSiteScopeGuard`);
expanding it now would increase data-isolation risk. Search remains a planned
performance phase because the current database driver still uses bounded
`LIKE` matching.

## Implementation

- Added `Cms/PublicSite/PublicNavigation` as the missing domain boundary for
  menu validation and rendering.
- Added the site-scoped `site.primary_navigation` JSON setting to the existing
  `CoreSettings` registry.
- Accepted only bounded, app-local paths; external and protocol-relative URLs,
  control characters, traversal-like paths, oversized labels, excessive item
  counts, and nesting deeper than two levels are rejected.
- Reused `AdminRuntimeUrl::appPath()` so links stay inside the active Pinoox
  mount, including `/qwe`-style deployments.
- Updated the existing home, content, and taxonomy renderers to consume the
  same optional navigation object.
- Corrupt optional menu data is ignored as presentation data; the public page
  remains available and cannot render an unsafe link.

## Validation

- PHP lint: passed for all changed PHP files.
- CMS PHP runtime suite: **59 passed, 0 failed**.
- Admin contract suite: **169 passed, 0 failed**.
- `git diff --check`: passed.
- Release app fingerprint refreshed from the actual `app.php` hash.
- Deployment/live browser verification: **Not executed in this phase**.

## Remaining gaps

- The Settings UI currently edits the menu as validated JSON rather than a
  drag-and-drop menu builder. A dedicated editor should be considered only
  after confirming that menu authoring is a recurring UX bottleneck.
- Full multi-site administration, signed package trust-chain E2E, target-host
  migration evidence, and browser viewport coverage remain open audit items.
