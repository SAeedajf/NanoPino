# NanoPino PHP Runtime Tests

This suite executes NanoPino PHP classes directly without adding development dependencies to the PINX payload.

Run:

```bash
php tests/php/run.php
```

Coverage currently includes:
- CSP policy generation and fail-closed validation;
- SSRF allowlist/DNS/IP enforcement;
- Recovery Manager orchestration and failure handling;
- Security posture behavior;
- package/release/documentation contracts.

GitHub CI executes the suite on PHP 8.2, 8.3, 8.4 and 8.5. Full Pinoox/database/browser E2E remains a separate target-environment release gate.
