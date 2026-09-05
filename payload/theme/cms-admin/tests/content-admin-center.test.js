import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createComponent } from '../runtime/content.mjs'

const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')
const fake = () => ({})

test('runtime content center exposes professional control-plane behaviors', () => {
  const component = createComponent({ h: fake, LPage: fake, LPanel: fake, LButton: fake, LBadge: fake })
  assert.equal(component.name, 'CmsContentAdminCenter')
  assert.equal(typeof component.methods.runBulk, 'function')
  assert.equal(typeof component.methods.schedule, 'function')
  assert.equal(typeof component.methods.nextPage, 'function')
  assert.equal(typeof component.methods.fieldControl, 'function')
})

test('runtime content center uses registered content types and fields instead of article/raw document JSON', () => {
  const runtime = read('runtime/content.mjs')
  assert.match(runtime, /result\.types/)
  assert.match(runtime, /descriptor\.fields/)
  assert.match(runtime, /fields:/)
  assert.match(runtime, /type: 'post'/)
  assert.doesNotMatch(runtime, /type: 'article'/)
  assert.doesNotMatch(runtime, /Document JSON/)
})

test('runtime content center wires publish schedule revisions bulk actions and pagination', () => {
  const runtime = read('runtime/content.mjs')
  assert.match(runtime, /\/schedule`/)
  assert.match(runtime, /revisions\?content=/)
  assert.match(runtime, /runBulk/)
  assert.match(runtime, /has_more/)
  assert.match(runtime, /offset/)
  assert.match(runtime, /content_page\.save_publish/)
})

test('canonical Vue content page mirrors the runtime content center contracts', () => {
  const page = read('src/pages/content/page-content.vue')
  assert.match(page, /currentFields/)
  assert.match(page, /runBulk/)
  assert.match(page, /scheduleContent/)
  assert.match(page, /openHistory/)
  assert.match(page, /pagination\.has_more/)
  assert.doesNotMatch(page, /Custom Fields \(JSON\)/)
})

test('canonical content API exposes schedule operation', () => {
  const api = read('src/services/cms-api.js')
  assert.match(api, /schedule:\(id,publishAt\)=>cmsRequest\(`\/content\/\$\{id\}\/schedule`/)
})
