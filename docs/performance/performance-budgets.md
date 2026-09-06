# Performance Budgets

Performance Center reads live runtime telemetry where a probe is bound. Supported evidence includes budget evaluation, query count/time, N+1 findings, slow queries, memory, cache telemetry, extension cost and recent samples.

Values that are not measured must remain unmeasured; the admin must not invent benchmark values.

The default profile is `shared_hosting`. Production performance claims require measurements on the target host and representative data volume.
