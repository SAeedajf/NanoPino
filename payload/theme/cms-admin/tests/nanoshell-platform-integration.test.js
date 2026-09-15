import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve('.')
const read = (path) => readFileSync(resolve(root, path), 'utf8')

test('NanoShell identity is consumed by the Admin boot and document shell', () => {
  const main = read('src/main.js')
  const platform = read('src/services/nanoshell-platform.js')
  assert.match(main, /applyNanoShellIdentity\(\)/)
  assert.match(platform, /dataset\.cmsPlatform/)
  assert.match(platform, /dataset\.cmsPlatformContract/)
  assert.match(platform, /nanoshell-platform-v1/)
  assert.match(platform, /auth\.rbac/)
  assert.match(platform, /minimum_version/)
  assert.match(platform, /cmsPlatformSupports/)
  assert.match(platform, /cachedPlatform/)
})

test('NanoShell identity is exposed to theme configuration and extension hosts', () => {
  const theme = read('src/registry/theme-config.js')
  const registry = read('src/registry/admin-components.js')
  assert.match(theme, /cmsPlatform\(\)/)
  assert.match(theme, /platform:\s*\{/)
  assert.match(registry, /platform: cmsPlatform\(\)/)
  assert.match(registry, /platformSupports: cmsPlatformSupports/)
  assert.match(registry, /deferredLumaComponent/)
  assert.match(registry, /@pinooxhq\/luma\/ui\/l-page\.vue/)
})

test('direct runtime modules use the same NanoShell contract and System Center displays it', () => {
  const common = read('runtime/common.mjs')
  const system = read('runtime/system.mjs')
  const provider = read('src/services/admin-provider.js')
  const systemPage = read('src/pages/system/page-system.vue')
  assert.match(common, /export function platform\(\)/)
  assert.match(common, /nanoshell-platform-v1/)
  assert.match(common, /cachedPlatformValue/)
  assert.match(common, /export function platformSupports\(capability\)/)
  assert.match(common, /host_bindings:'pinoox-native'/)
  assert.match(systemPage, /platform\.capabilities/)
  assert.match(systemPage, /platform\.compatibility/)
  assert.match(system, /platform\}/)
  assert.match(system, /current\.contract/)
  assert.match(provider, /contract: 'nanoshell-platform-v1'/)
})
