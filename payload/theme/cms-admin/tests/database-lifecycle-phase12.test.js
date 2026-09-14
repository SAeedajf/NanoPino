import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(fileURLToPath(new URL('../../../', import.meta.url)))
const read = (path) => readFileSync(resolve(packageRoot, path), 'utf8')

test('package database binding preserves native platform production while allowing isolated local SQLite', () => {
  const app = read('app.php')
  const probe = read('Cms/Installer/Installability/PinooxInstallabilityProbe.php')

  assert.match(app, /use'\s*=>\s*'platform'/)
  assert.match(app, /\['mysql', 'mariadb'\]/)
  assert.match(app, /env\('DB_CONNECTION', ''\)/)
  assert.match(probe, /\$driver === 'sqlite' && \$this->isLocalSqliteRuntime\(\)/)
  assert.match(probe, /install\.database_driver_unsupported/)
  assert.match(probe, /PINOOX_TESTING/)
})

test('CMS migrations are ordered, idempotent, repair legacy prefixes, and keep repair rollback non-destructive', () => {
  const migrationDir = resolve(packageRoot, 'database/migrations')
  const files = [
    '2026_09_01_000000_preflight_nanopino_environment.php',
    '2026_09_01_080000_create_cms_settings_table.php',
    '2026_09_01_090000_create_cms_contents_table.php',
    '2026_09_02_190000_repair_cms_required_schema.php',
    '2026_09_03_000000_repair_cms_logical_table_contract.php',
    '2026_09_10_180000_add_cms_relational_constraints.php',
  ]
  const sources = files.map((file) => readFileSync(resolve(migrationDir, file), 'utf8'))

  assert.match(sources[0], /PinooxInstallabilityProbe/)
  assert.match(sources[1], /hasTable\('settings'\)/)
  assert.match(sources[2], /hasTable\('contents'\)/)
  assert.match(sources[3], /CANONICAL_MIGRATIONS/)
  assert.match(sources[3], /down\(\): void\s*\{\s*\/\/ No-op/s)
  assert.match(sources[4], /schema->rename\(\$legacyLogical, \$logical\)/)
  assert.match(sources[4], /down\(\): void\s*\{\s*\/\/ No-op/s)
  assert.match(sources[5], /hasForeignKey/)
  assert.match(sources[5], /on_delete/)
})

test('install and uninstall lifecycle use migration rollback and refuse to delete files when owned tables remain', () => {
  const lifecycle = read('lifecycle.php')

  assert.match(lifecycle, /new Migrator\(/)
  assert.match(lifecycle, /'rollback'/)
  assert.match(lifecycle, /'force'\s*=>\s*true/)
  assert.match(lifecycle, /'use_transactions'\s*=>\s*true/)
  assert.match(lifecycle, /ownedTables/)
  assert.match(lifecycle, /Refusing to delete application files/)
})
