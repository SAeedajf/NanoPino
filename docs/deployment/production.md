# Production Deployment

Production deployment must use a verified build, not an unbuilt source checkout.

Required evidence includes production Vue/Luma build success, source verification, PHP lint/tests, machine API contract parity, PINX metadata/version consistency, target database migrations, real-mount admin loading, security headers/CSRF/rate limits and critical content/media/settings/users/builder/extension flows.

CSP is enforced by default; set `PINOOX_CMS_CSP_MODE=report-only` only as a temporary rollback while diagnosing a verified compatibility issue. Remote Search should remain Database mode unless a trusted HTTPS endpoint is intentionally configured.
