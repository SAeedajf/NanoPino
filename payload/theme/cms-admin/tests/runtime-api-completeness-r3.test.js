import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const runtime = readFileSync(resolve('../../Cms/Runtime/CmsRuntimeApiManifest.php'), 'utf8')
const app = readFileSync(resolve('../../app.php'), 'utf8')
const manifest = JSON.parse(readFileSync(resolve('../../../manifest.json'), 'utf8'))

test('R3 binds every advertised core runtime API surface', () => {
  for (const path of [
    "'/search'",
    "'/system/infrastructure'",
    "'/system/infrastructure/queue'",
    "'/system/performance'",
    "'/updates/{id}/policy'",
    "'/updates/{id}/history'",
    "'/updates/{id}/recovery-points'",
  ]) assert.match(runtime, new RegExp(path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))

  for (const controller of [
    'SearchRuntimeApiController',
    'InfrastructureRuntimeApiController',
    'PerformanceRuntimeApiController',
    'UpdateRuntimeApiController',
  ]) assert.match(runtime, new RegExp(controller))
})

test('NanoPino rename is non-breaking for package compatibility', () => {
  assert.equal(manifest.name, 'NanoPino')
  assert.equal(manifest.package, 'com_pinoox_cms')
  const appVersion = app.match(/'version-name'\s*=>\s*'([^']+)'/)?.[1]
  const appCode = Number(app.match(/'version-code'\s*=>\s*(\d+)/)?.[1] || 0)
  assert.equal(manifest.version_name, appVersion)
  assert.equal(manifest.version_code, appCode)
  assert.match(app, /'title' => 'NanoPino'/)
  assert.match(app, /'package' => 'com_pinoox_cms'/)
  assert.match(app, /'compatibility_mode' => 'non-breaking-brand-rename'/)
})
