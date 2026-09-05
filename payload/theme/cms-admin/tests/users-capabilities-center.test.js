import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createComponent } from '../runtime/users.mjs'

const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')
const fake = () => ({})

test('runtime users center exposes identity administration behaviors', () => {
  const component = createComponent({ h: fake, LPage: fake, LPanel: fake, LButton: fake, LBadge: fake, LStatCard: fake })
  assert.equal(component.name, 'CmsUsersCapabilitiesCenter')
  assert.equal(typeof component.methods.load, 'function')
  assert.equal(typeof component.methods.toggleRole, 'function')
  assert.equal(typeof component.methods.revoke, 'function')
  assert.equal(typeof component.methods.next, 'function')
})

test('runtime users center exposes server filters pagination and role capability catalog', () => {
  const runtime = read('runtime/users.mjs')
  assert.match(runtime, /roleTemplates/)
  assert.match(runtime, /capabilities/)
  assert.match(runtime, /has_more/)
  assert.match(runtime, /filters:\{q:'',status:'',role:''\}/)
  assert.match(runtime, /users_page\.registry/)
})

test('runtime users center has explicit anti-lockout controls', () => {
  const runtime = read('runtime/users.mjs')
  assert.match(runtime, /users_page\.self_delete_block/)
  assert.match(runtime, /users_page\.self_status_block/)
  assert.match(runtime, /users_page\.self_role_block/)
  assert.match(runtime, /users_page\.revoke_self_confirm/)
})

test('canonical Vue users page mirrors operational RBAC contracts', () => {
  const page = read('src/pages/users/page-users.vue')
  assert.match(page, /users_page\.roles_caps/)
  assert.match(page, /users_page\.registry/)
  assert.match(page, /pagination\.has_more/)
  assert.match(page, /users\.roles\.manage/)
  assert.doesNotMatch(page, /window\.prompt/)
})

test('canonical user API accepts server-side list parameters', () => {
  const api = read('src/services/cms-api.js')
  assert.match(api, /list:\(params=\{\}\)=>/)
  assert.match(api, /URLSearchParams/)
})
