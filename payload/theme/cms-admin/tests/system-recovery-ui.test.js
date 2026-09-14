import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))

test('system and logs Vue centers expose visible retry recovery for API failures', () => {
  const logs = readFileSync(resolve(themeRoot, 'src/pages/system/page-logs.vue'), 'utf8')
  const system = readFileSync(resolve(themeRoot, 'src/pages/system/page-system.vue'), 'utf8')
  const dashboard = readFileSync(resolve(themeRoot, 'src/pages/dashboard/page-dashboard-final.vue'), 'utf8')
  assert.match(logs, /v-if="error"[^>]*role="alert"/)
  assert.match(logs, /@click="load".*t\('state\.retry'\)/s)
  assert.match(logs, /catch\(e\)\{error\.value=e\.message\}/)
  assert.match(system, /v-if="error"[^>]*role="alert"/)
  assert.match(system, /catch\(e\)\{error\.value=e\.message\}/)
  assert.match(system, /:loading="loading".*@click="load"/)
  assert.match(system, /Promise\.allSettled/)
  assert.match(dashboard, /Promise\.allSettled/)
  assert.match(dashboard, /partial_load_failed/)
})
