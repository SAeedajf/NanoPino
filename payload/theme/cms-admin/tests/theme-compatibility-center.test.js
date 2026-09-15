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
  assert.equal(typeof component.methods.selectWordPress, 'function')
  assert.equal(typeof component.methods.previewWordPress, 'function')
  assert.equal(typeof component.methods.installWordPress, 'function')
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
  assert.match(page, /wordpressPreview/)
  assert.match(page, /accept="\.zip,application\/zip"/)
  assert.match(page, /wordpressInstall/)
  assert.match(page, /wordpress_install_confirm/)
})

test('Theme Center hydrates from the server boot payload before refreshing its API data', () => {
  const page = read('src/pages/appearance/page-appearance.vue')
  const runtime = read('runtime/appearance.mjs')
  assert.match(page, /const boot=readAdminBootData\(\)/)
  assert.match(page, /Array\.isArray\(boot\.themes\)/)
  assert.match(page, /loading && !themes\.length/)
  assert.match(runtime, /Array\.isArray\(boot\.themes\)/)
  assert.match(runtime, /this\.loading&&!this\.items\.length/)
  assert.match(runtime, /themes\/wordpress\/preview/)
  assert.match(runtime, /this\.wpPreview/)
  assert.match(runtime, /themes\/wordpress\/install/)
  assert.match(runtime, /confirmation','INSTALL'/)
})

test('selecting an original ZIP automatically starts inspection before installation', () => {
  const page = read('src/pages/appearance/page-appearance.vue')
  const runtime = read('runtime/appearance.mjs')
  assert.match(page, /async function selectWordPress\(event\).*if\(wordpressFile\.value\)await previewWordPress\(\)/)
  assert.match(runtime, /async selectWordPress\(e\).*if\(this\.wpFile\)await this\.previewWordPress\(\)/)
  assert.match(page, /themeApi\.wordpressPreview\(wordpressFile\.value\)/)
  assert.match(runtime, /form\.append\('file',this\.wpFile\)/)
  assert.match(page, /wordpress_retry/)
  assert.match(runtime, /wordpress_retry/)
})

test('theme install remains usable when the host browser has no native confirm dialog', () => {
  const page = read('src/pages/appearance/page-appearance.vue')
  const runtime = read('runtime/appearance.mjs')
  assert.match(page, /typeof window!==['"]undefined['"]&&typeof window\.confirm===['"]function['"]&&!confirm/)
  assert.match(runtime, /typeof window!==['"]undefined['"]&&typeof window\.confirm===['"]function['"]&&!confirmFa/)
})

test('Theme Center exposes install versus update details and keeps downgrade messaging visible', () => {
  const page = read('src/pages/appearance/page-appearance.vue')
  const runtime = read('runtime/appearance.mjs')
  for (const source of [page, runtime]) {
    assert.match(source, /wordpress_update_install/)
    assert.match(source, /wordpress_update_versions/)
    assert.match(source, /wordpress_update_changes/)
    assert.match(source, /wordpress_update_downgrade_blocked/)
  }
})
