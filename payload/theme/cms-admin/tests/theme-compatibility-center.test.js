import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createComponent } from '../runtime/appearance.mjs'

const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')
const fake = () => ({})

test('runtime Theme Center exposes compatibility-aware activation surface', () => {
  const component = createComponent({ h: fake, LPage: fake, LPanel: fake, LButton: fake, LBadge: fake, LStatCard: fake })
  assert.equal(component.name, 'CmsAppearanceControlPlane')
  assert.equal(typeof component.methods.healthy, 'function')
  assert.equal(typeof component.methods.issues, 'function')
  assert.equal(typeof component.methods.activate, 'function')
})

test('runtime Theme Center blocks incompatible themes and exposes inheritance context', () => {
  const runtime = read('runtime/appearance.mjs')
  assert.match(runtime, /compatibility\?\.compatible/)
  assert.match(runtime, /inheritance\?\.valid/)
  assert.match(runtime, /inheritance\?\.chain/)
  assert.match(runtime, /Compatibility Blocked/)
  assert.match(runtime, /disabled:isActive\|\|!healthy\|\|!this\.canActivate/)
  assert.match(runtime, /routes\.appearance\.lead/)
  assert.match(runtime, /Pinoox-compatible Theme Center/)
  assert.doesNotMatch(runtime, /[\u0600-\u06FF]/)
})

test('canonical Vue Theme Center mirrors compatibility and safe activation contract', () => {
  const page = read('src/pages/appearance/page-appearance.vue')
  assert.match(page, /summary\.compatible/)
  assert.match(page, /summary\.blocked/)
  assert.match(page, /theme\.inheritance\.chain/)
  assert.match(page, /!healthy\(theme\)/)
  assert.match(page, /canActivate/)
})
