# Structured Logging

NanoPino uses structured CMS logs with correlation-aware diagnostic context. Logging is file-backed in the current baseline and consumed by System/Logs and Support Bundle flows.

Security-sensitive keys, credentials, tokens, authorization values, API keys and private keys must be redacted before persistence/export.

Support Bundles are diagnostics, not backups: they must not include media/content bytes or credentials. Correlation IDs connect request, audit, queue and error records.
