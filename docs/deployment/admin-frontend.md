# Admin Frontend Deployment

The canonical admin source is `payload/theme/cms-admin/src`. Production assets are built with the package build script and verified against source/runtime fingerprints.

CI runs `npm ci` from the lockfile before `npm run build`. A stale `dist` must not be relabelled as current.

The server probes frontend assets before rendering the admin. Missing or invalid assets return a diagnosable unavailable state rather than a white screen.

Shared hosting should receive the already-built verified `dist`; Node/npm is not required at runtime.
