import test from 'node:test'
import assert from 'node:assert/strict'
import { canAdmin } from '../src/services/admin-provider.js'
import { readFileSync } from 'node:fs'

const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')

test('client permission helper supports exact, wildcard and denied capabilities', () => {
  const data = { currentUser: { abilities: ['content.read', 'media.*'] } }
  assert.equal(canAdmin('content.read', data), true)
  assert.equal(canAdmin('content.publish', data), false)
  assert.equal(canAdmin('media.delete', data), true)
  assert.equal(canAdmin('users.read', { currentUser: { abilities: [] } }), false)
  assert.equal(canAdmin('content.read', { authState: 'anonymous', currentUser: null }), false)
  assert.equal(canAdmin('content.read', { authState: 'authenticated', currentUser: null }), false)
})

test('high-impact admin pages gate mutation affordances before calling the API', () => {
  const content = read('src/pages/content/page-content.vue')
  const media = read('src/pages/media/page-media.vue')
  const extensions = read('src/pages/extensions/page-extensions.vue')
  const updates = read('src/pages/updates/page-updates.vue')

  assert.match(content, /canCreate=canAdmin\('content\.create'\)/)
  assert.match(content, /!canPublish/)
  assert.match(media, /canUpload=canAdmin\('media\.upload'\)/)
  assert.match(media, /!canDelete/)
  assert.match(extensions, /canInstall=canAdmin\('extensions\.install'\)/)
  assert.match(extensions, /canUninstall/)
  assert.match(updates, /canManage\s*=\s*canAdmin\('extensions\.update'\)/)

  const common = read('runtime/common.mjs')
  const runtimeContent = read('runtime/content.mjs')
  const runtimeMedia = read('runtime/media.mjs')
  const runtimeExtensions = read('runtime/extensions.mjs')
  assert.match(common, /export function can\(ability\)/)
  assert.match(runtimeContent, /permissions: \{ create: can\('content\.create'\)/)
  assert.match(runtimeMedia, /permissions:\{upload:can\('media\.upload'\)/)
  assert.match(runtimeExtensions, /permissions:\{install:can\('extensions\.install'\)/)
})
