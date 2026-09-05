import test from 'node:test'
import assert from 'node:assert/strict'
import { readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(fileURLToPath(new URL('../../..', import.meta.url)))
const apiRoot = resolve(packageRoot, 'resources/api')
const runtimeSource = readFileSync(resolve(packageRoot, 'Cms/Runtime/CmsRuntimeApiManifest.php'), 'utf8')

function runtimeRoutes() {
  const routes = new Map()
  for (const line of runtimeSource.split('\n')) {
    if (!line.includes('self::route(')) continue
    const values = [...line.matchAll(/'([^']*)'/g)].map((m) => m[1])
    if (values.length < 6) continue
    const [method, path, , , permission, rateLimit] = values
    routes.set(`${method} ${path}`, { permission, rateLimit })
  }
  return routes
}

function normalizePath(path) {
  return path.startsWith('/api/v1/cms') ? (path.slice('/api/v1/cms'.length) || '/') : path
}

function contractRoutes() {
  const routes = new Map()
  const files = readdirSync(apiRoot).filter((file) => file.endsWith('.json')).sort()
  for (const file of files) {
    const contract = JSON.parse(readFileSync(resolve(apiRoot, file), 'utf8'))
    if (Array.isArray(contract.routes)) {
      for (const route of contract.routes) {
        routes.set(`${route.method} ${normalizePath(route.path)}`, {
          permission: route.capability || route.permission || null,
          rateLimit: route.rate_limit || null,
          file,
        })
      }
      continue
    }
    for (const [key, route] of Object.entries(contract.endpoints || {})) {
      const split = key.indexOf(' ')
      const method = key.slice(0, split)
      const path = normalizePath(key.slice(split + 1))
      routes.set(`${method} ${path}`, {
        permission: route.capability || route.permission || null,
        rateLimit: route.rate_limit || null,
        file,
      })
    }
  }
  return { routes, files }
}

test('R4 machine contracts cover every public runtime route bidirectionally', () => {
  const runtime = runtimeRoutes()
  const { routes: contracts, files } = contractRoutes()

  assert.equal(files.length, 14)
  assert.equal(runtime.size, 66)
  assert.equal(contracts.size, 66)
  assert.deepEqual([...contracts.keys()].filter((key) => !runtime.has(key)), [])
  assert.deepEqual([...runtime.keys()].filter((key) => !contracts.has(key)), [])

  for (const [key, contract] of contracts) {
    const actual = runtime.get(key)
    if (contract.permission) assert.equal(actual.permission, contract.permission, `${key} permission`)
    if (contract.rateLimit) assert.equal(actual.rateLimit, contract.rateLimit, `${key} rate limit`)
  }
})
