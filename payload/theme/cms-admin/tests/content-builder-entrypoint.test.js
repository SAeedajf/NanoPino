import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = (file) => readFileSync(new URL(`../../../${file}`, import.meta.url), 'utf8')
const readTheme = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')

test('content management remains directly reachable under the nested content navigation group', () => {
  const definitions = read('Cms/Admin/CoreContentAdminDefinitions.php')
  assert.match(definitions, /'cms\.content\.index'/)
  assert.match(definitions, /'nav\.content_index'/)
  assert.match(definitions, /'cms\.content',\s*'content',\s*20/s)
})

test('Builder clearly separates creating content from creating its design override', () => {
  const vue = readTheme('src/pages/builder/page-builder.vue')
  const runtime = readTheme('runtime/builder.mjs')
  assert.match(vue, /useRouter/)
  assert.match(vue, /builder_page\.create_content/)
  assert.match(vue, /router\.push\('\/content'\)/)
  assert.match(runtime, /navigate\('\/content'\)/)
})

test('taxonomy management is a real admin surface, not a read-only picker', () => {
  const definitions = read('Cms/Admin/CoreContentAdminDefinitions.php')
  const registry = readTheme('src/registry/admin-routes.js')
  const page = readTheme('src/pages/taxonomies/page-taxonomies.vue')
  const api = readTheme('src/services/cms-api.js')
  assert.match(definitions, /'cms\.taxonomies'/)
  assert.match(definitions, /'taxonomy\.read'/)
  assert.match(registry, /core:taxonomies.*page-taxonomies\.vue/)
  assert.match(page, /taxonomyApi\.create/)
  assert.match(page, /taxonomyApi\.update/)
  assert.match(page, /taxonomyApi\.delete/)
  assert.match(page, /taxonomy\.manage/)
  assert.match(api, /delete:\(key,id\)/)
})
