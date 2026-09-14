import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))

test('updates center preserves healthy sections when one API read fails', () => {
  const page = readFileSync(resolve(themeRoot, 'src/pages/updates/page-updates.vue'), 'utf8')
  assert.match(page, /Promise\.allSettled/)
  assert.match(page, /if \(p\.status === 'fulfilled'\)/)
  assert.match(page, /if \(h\.status === 'fulfilled'\)/)
  assert.match(page, /if \(r\.status === 'fulfilled'\)/)
  assert.match(page, /partial_load_failed/)
  assert.match(page, /let loadRequest = 0/)
  assert.match(page, /request !== loadRequest/)
})
