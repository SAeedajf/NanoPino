import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const readPackage = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')

function readPhpServices(source) {
  const match = source.match(/'services'\s*=>\s*\[(.*?)\n\s*\],/s)
  assert.ok(match, 'app.php must declare a CMS services array')
  return [...match[1].matchAll(/'([^']+)'/g)].map((item) => item[1])
}

function assertUnique(values, label) {
  assert.equal(new Set(values).size, values.length, `${label} contains duplicate entries`)
  assert.ok(values.every((value) => /^[a-z0-9][a-z0-9-]*$/.test(value)), `${label} contains invalid service ids`)
}

test('phase 9 keeps app.php, manifest.json and the canonical service registry identical', () => {
  const canonical = JSON.parse(readPackage('resources/release/service-registry-parity-v1.json'))
  const manifest = JSON.parse(readPackage('manifest.json'))
  const appServices = readPhpServices(readPackage('app.php'))
  const manifestServices = manifest.cms?.services || []

  assert.equal(canonical.package, manifest.package)
  assert.equal(canonical.package, 'com_pinoox_cms')
  assert.equal(canonical.version, manifest.version_name)
  assert.deepEqual(manifest.cms?.service_registry, {
    schema: 1,
    source: 'resources/release/service-registry-parity-v1.json',
    count: canonical.services.length,
  })
  assert.match(readPackage('app.php'), /'service_registry'\s*=>\s*\[[\s\S]*?'count'\s*=>\s*91/s)
  assertUnique(canonical.services, 'canonical services')
  assertUnique(appServices, 'app.php services')
  assertUnique(manifestServices, 'manifest services')
  assert.deepEqual(appServices, canonical.services)
  assert.deepEqual(manifestServices, canonical.services)
})

test('phase 9 has no orphan core route components or missing page modules', () => {
  const routesSource = readPackage('theme/cms-admin/src/registry/admin-routes.js')
  const registered = [...routesSource.matchAll(/'([^']+)'\s*:\s*\(\)\s*=>\s*import\('([^']+)'\)/g)]
    .map(([, id, importPath]) => ({ id, importPath }))
  const routeIds = []

  for (const file of readdirSync(resolve(packageRoot, 'Cms/Admin')).filter((name) => name.startsWith('Core') && name.endsWith('Definitions.php'))) {
    const source = readPackage(`Cms/Admin/${file}`)
    for (const line of source.split('\n')) {
      if (line.includes('core:') && !line.includes('AdminWidgetDefinition')) {
        routeIds.push(...(line.match(/core:[a-z0-9-]+/g) || []))
      }
    }
  }

  const registeredIds = registered.map(({ id }) => id)
  assert.deepEqual([...new Set(routeIds)].sort(), registeredIds.sort())

  for (const { id, importPath } of registered) {
    assert.ok(existsSync(resolve(themeRoot, 'src/registry', importPath)), `${id} imports a missing page module`)
  }
})

test('phase 9 marks non-canonical dashboards as legacy and keeps prototype code out of release scope', () => {
  const registry = JSON.parse(readPackage('resources/release/service-registry-parity-v1.json'))
  const routes = readPackage('theme/cms-admin/src/registry/admin-routes.js')
  const app = readPackage('app.php')

  assert.match(routes, /core:dashboard.*page-dashboard-final\.vue/)
  assert.equal(registry.frontend.canonical_dashboard_source, 'theme/cms-admin/src/pages/dashboard/page-dashboard-final.vue')
  for (const legacy of registry.frontend.legacy_dashboard_sources) {
    assert.ok(existsSync(resolve(packageRoot, legacy)), `${legacy} must be retained until its removal decision`)
  }
  for (const excluded of registry.frontend.development_only_exclusions) {
    assert.match(app, new RegExp(excluded.replaceAll('/', '\\/')))
  }
})
