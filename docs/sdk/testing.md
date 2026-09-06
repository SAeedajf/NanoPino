# SDK Testing

An extension should test:
- manifest/schema validity;
- ownership and registry collisions;
- permission/capability requirements;
- API route and request validation;
- package inspection and static preflight;
- install/update/uninstall behavior;
- recovery behavior on thrown exceptions and unsuccessful native results;
- admin loading under the active mount path;
- RTL/mobile/accessibility behavior for admin surfaces.

NanoPino repository CI currently provides contract/regression coverage and PHP lint. Target-runtime integration tests remain required for release claims.
