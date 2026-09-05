# Changelog

## 0.23.27 RC11

- Adopt source-first release/update workflow.
- Add reproducible version bump, source verification and native PINX build tooling.
- Require a dedicated Pinoox build workspace; installed runtime apps are never overwritten during package build.
- Keep GitHub source-only: no `.pinx`, ZIP or dependency directories in the repository.
- Add RC11 update contract evidence.

## 0.23.26 RC10

- Fix package database engine inheritance on hosts where `DB_ENGINE` is empty by explicitly selecting InnoDB while reusing the platform connection.
- Complete Admin i18n migration and remove hard-coded Persian UI copy / forced RTL locale assumptions.
- Fresh-install validation completed all 18 migrations and required CMS tables.
