# Shared Hosting Deployment

NanoPino's baseline assumes shared hosting may have no SSH, long-running worker or Node runtime.

Build Vue/Luma assets on a development machine or CI and include the verified `dist` in the final package. Runtime does not require Node.

The file queue supports shared-hosting fallback behavior; scheduled triggers may be used without a permanent worker.

Keep database migrations bounded and avoid deployment steps that assume shell access. Recovery files are stored under the CMS storage root and should be writable only by the application account.
