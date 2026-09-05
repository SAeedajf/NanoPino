import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createComponent } from '../runtime/media.mjs'

const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')
const fake = () => ({})

test('runtime media center exposes operational library behaviors', () => {
  const component = createComponent({ h: fake, LPage: fake, LPanel: fake, LButton: fake, LBadge: fake })
  assert.equal(component.name, 'CmsMediaAdminCenter')
  assert.equal(typeof component.methods.runUploads, 'function')
  assert.equal(typeof component.methods.openDetail, 'function')
  assert.equal(typeof component.methods.saveMetadata, 'function')
  assert.equal(typeof component.methods.nextPage, 'function')
  assert.equal(typeof component.methods.remove, 'function')
})

test('runtime media center uses server-side policy pagination and deletion safety', () => {
  const runtime = read('runtime/media.mjs')
  assert.match(runtime, /upload_policy/)
  assert.match(runtime, /allowed_extensions/)
  assert.match(runtime, /has_more/)
  assert.match(runtime, /MEDIA_IN_USE/)
  assert.match(runtime, /usage_count/)
  assert.match(runtime, /variant_count/)
})

test('runtime media center covers mobile grid list and accessibility metadata', () => {
  const runtime = read('runtime/media.mjs')
  assert.match(runtime, /missing_alt/)
  assert.match(runtime, /media_page\.no_alt/)
  assert.match(runtime, /view==='grid'/)
  assert.match(runtime, /@media\(max-width:700px\)/)
  assert.match(runtime, /media_page\.focal_x/)
})

test('canonical Vue media page mirrors runtime operational contracts', () => {
  const page = read('src/pages/media/page-media.vue')
  assert.match(page, /media_page\.secure_upload/)
  assert.match(page, /mediaApi\.read/)
  assert.match(page, /media_page\.usages/)
  assert.match(page, /media_page\.variants/)
  assert.match(page, /pagination\.has_more/)
  assert.doesNotMatch(page, /Upload API هنوز publish نشده است/)
})

test('canonical media API exposes detail read operation', () => {
  const api = read('src/services/cms-api.js')
  assert.match(api, /read:id=>cmsRequest\(`\/media\/\$\{id\}`\)/)
})
